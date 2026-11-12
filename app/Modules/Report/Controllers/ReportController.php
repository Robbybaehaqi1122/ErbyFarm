<?php

namespace App\Modules\Report\Controllers;

use App\Modules\Farm\Models\Farm;
use App\Modules\Production\Models\DailyProduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    /**
     * Build the farm performance report for the selected period.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole(['admin', 'operator', 'viewer'])) {
            abort(403, 'You are not allowed to view reports.');
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'farm_id' => ['nullable', 'integer', Rule::exists(Farm::class, 'id')],
            'group_by' => ['nullable', Rule::in(['day', 'month'])],
        ]);

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->startOfDay()
            : now()->startOfDay();

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : $to->copy()->startOfMonth();

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                'from' => 'Tanggal awal tidak boleh melebihi tanggal akhir.',
            ]);
        }

        $groupBy = $validated['group_by'] ?? 'day';

        $productions = DailyProduction::query()
            ->with([
                'livestock:id,farm_id,current_population,entry_date',
                'livestock.farm:id,name',
            ])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when(isset($validated['farm_id']), function ($query) use ($validated): void {
                $query->whereHas('livestock', function ($query) use ($validated): void {
                    $query->where('farm_id', $validated['farm_id']);
                });
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $mortalityMap = $this->buildMortalityMap($productions, $from);
        $rows = $this->buildRows($productions, $mortalityMap);

        $byFarm = $this->groupByFarm($rows);
        $periods = $this->groupByPeriod($rows, $groupBy);

        return response()->json([
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'group_by' => $groupBy,
                'farm_id' => isset($validated['farm_id']) ? (int) $validated['farm_id'] : null,
            ],
            'summary' => array_merge($this->aggregate($rows), [
                'farm_count' => count($byFarm),
            ]),
            'by_farm' => $byFarm,
            'periods' => $periods,
        ]);
    }

    /**
     * Reconstruct the live population for each flock and date.
     *
     * `current_population` is the population after all recorded mortality,
     * so adding back the mortality on/after a date gives the population at
     * the start of that date.
     *
     * @param  Collection<int, DailyProduction>  $productions
     * @return array<int, array<string, int>>
     */
    private function buildMortalityMap(Collection $productions, Carbon $from): array
    {
        $livestockIds = $productions->pluck('livestock_id')->unique()->values();

        if ($livestockIds->isEmpty()) {
            return [];
        }

        $rowsByLivestock = DailyProduction::query()
            ->whereIn('livestock_id', $livestockIds)
            ->where('date', '>=', $from->toDateString())
            ->orderBy('date')
            ->get(['livestock_id', 'date', 'mortality'])
            ->groupBy('livestock_id');

        $mortalityMap = [];

        foreach ($rowsByLivestock as $livestockId => $rows) {
            $cumulative = 0;
            $dates = [];

            foreach ($rows->sortByDesc(fn (DailyProduction $row) => (string) $row->date) as $row) {
                $cumulative += (int) $row->mortality;
                $dates[(string) $row->date] = $cumulative;
            }

            $mortalityMap[(int) $livestockId] = $dates;
        }

        return $mortalityMap;
    }

    /**
     * Transform production records into report rows with population data.
     *
     * @param  Collection<int, DailyProduction>  $productions
     * @param  array<int, array<string, int>>  $mortalityMap
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRows(Collection $productions, array $mortalityMap): Collection
    {
        return $productions->map(function (DailyProduction $production) use ($mortalityMap): array {
            $date = (string) $production->date;
            $livestock = $production->livestock;
            $mortalityOnOrAfterDate = $mortalityMap[$production->livestock_id][$date] ?? 0;

            return [
                'date' => $date,
                'farm_id' => (int) $livestock->farm_id,
                'farm_name' => $livestock->farm?->name ?? '-',
                'eggs' => (int) $production->egg_count,
                'egg_weight_kg' => (float) $production->egg_weight_kg,
                'feed_kg' => (float) $production->feed_consumed_kg,
                'water_liter' => (float) $production->water_consumed_liter,
                'mortality' => (int) $production->mortality,
                'population' => max(
                    0,
                    (int) $livestock->current_population + $mortalityOnOrAfterDate,
                ),
            ];
        });
    }

    /**
     * Group report rows by farm.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupByFarm(Collection $rows): array
    {
        return $rows
            ->groupBy('farm_id')
            ->map(function (Collection $group, int|string $farmId): array {
                return array_merge([
                    'farm_id' => (int) $farmId,
                    'farm_name' => $group->first()['farm_name'],
                ], $this->aggregate($group));
            })
            ->sortByDesc('total_eggs')
            ->values()
            ->all();
    }

    /**
     * Group report rows by day or month.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupByPeriod(Collection $rows, string $groupBy): array
    {
        return $rows
            ->groupBy(fn (array $row): string => $groupBy === 'month'
                ? substr($row['date'], 0, 7)
                : $row['date'])
            ->map(function (Collection $group, string $period): array {
                return array_merge([
                    'period' => $period,
                ], $this->aggregate($group));
            })
            ->sortBy('period')
            ->values()
            ->all();
    }

    /**
     * Aggregate production metrics for a set of rows.
     *
     * Hen-Day (%) = total eggs / total live hen-days × 100.
     * Feed and water averages are per recorded day across all flocks.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int|float>
     */
    private function aggregate(Collection $rows): array
    {
        $totalEggs = (int) $rows->sum('eggs');
        $totalEggWeight = (float) $rows->sum('egg_weight_kg');
        $totalFeed = (float) $rows->sum('feed_kg');
        $totalWater = (float) $rows->sum('water_liter');
        $totalMortality = (int) $rows->sum('mortality');
        $henDays = (int) $rows->sum('population');
        $recordedDays = $rows->pluck('date')->unique()->count();

        return [
            'total_eggs' => $totalEggs,
            'egg_weight_kg' => round($totalEggWeight, 2),
            'total_feed_kg' => round($totalFeed, 2),
            'average_feed_kg' => $recordedDays > 0 ? round($totalFeed / $recordedDays, 2) : 0.0,
            'total_water_liter' => round($totalWater, 2),
            'average_water_liter' => $recordedDays > 0 ? round($totalWater / $recordedDays, 2) : 0.0,
            'total_mortality' => $totalMortality,
            'average_mortality' => $recordedDays > 0 ? round($totalMortality / $recordedDays, 2) : 0.0,
            'average_egg_weight_gram' => $totalEggs > 0 ? round(($totalEggWeight * 1000) / $totalEggs, 1) : 0.0,
            'hen_day' => $henDays > 0 ? round(($totalEggs / $henDays) * 100, 2) : 0.0,
            'recorded_days' => $recordedDays,
        ];
    }
}
