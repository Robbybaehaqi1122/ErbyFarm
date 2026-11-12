<?php

namespace App\Modules\Production\Controllers;

use App\Modules\Livestock\Models\Livestock;
use App\Modules\Production\Models\DailyProduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DailyProductionController extends Controller
{
    /**
     * Display a listing of daily production records.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DailyProduction::class);

        $search = $request->string('search')->trim()->toString();

        $productions = DailyProduction::query()
            ->with([
                'livestock:id,farm_id,entry_date,current_population,status',
                'livestock.farm:id,name,capacity',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('livestock.farm', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('livestock_id'), function ($query) use ($request): void {
                $query->where('livestock_id', $request->integer('livestock_id'));
            })
            ->when($request->filled('from'), function ($query) use ($request): void {
                $query->whereDate('date', '>=', $request->string('from')->toString());
            })
            ->when($request->filled('to'), function ($query) use ($request): void {
                $query->whereDate('date', '<=', $request->string('to')->toString());
            })
            ->latest('date')
            ->latest('id')
            ->paginate($request->integer('per_page', 31))
            ->appends(request()->query());

        return response()->json($productions);
    }

    /**
     * Store a newly created daily production record.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', DailyProduction::class);

        $validated = $request->validate([
            'livestock_id' => ['required', 'integer', Rule::exists(Livestock::class, 'id')],
            'date' => [
                'required',
                'date',
                Rule::unique('daily_productions')->where(
                    fn ($query) => $query
                        ->where('livestock_id', $request->integer('livestock_id'))
                        ->whereDate('date', $request->input('date'))
                ),
            ],
            'egg_count' => ['required', 'integer', 'min:0'],
            'egg_weight_kg' => ['required', 'numeric', 'min:0'],
            'feed_consumed_kg' => ['required', 'numeric', 'min:0'],
            'water_consumed_liter' => ['required', 'numeric', 'min:0'],
            'mortality' => ['required', 'integer', 'min:0'],
        ]);

        $dailyProduction = DB::transaction(function () use ($validated): DailyProduction {
            $livestock = Livestock::query()->lockForUpdate()->findOrFail($validated['livestock_id']);

            $this->decreasePopulation($livestock, $validated['mortality']);

            return DailyProduction::create($validated);
        });

        return response()->json($dailyProduction->load('livestock.farm'), 201);
    }

    /**
     * Display the specified daily production record.
     */
    public function show(DailyProduction $dailyProduction): JsonResponse
    {
        Gate::authorize('view', $dailyProduction);

        return response()->json($dailyProduction->load('livestock.farm'));
    }

    /**
     * Update the specified daily production record.
     */
    public function update(Request $request, DailyProduction $dailyProduction): JsonResponse
    {
        Gate::authorize('update', $dailyProduction);

        $targetLivestockId = $request->input('livestock_id', $dailyProduction->livestock_id);

        $validated = $request->validate([
            'livestock_id' => ['sometimes', 'integer', Rule::exists(Livestock::class, 'id')],
            'date' => [
                'sometimes',
                'date',
                Rule::unique('daily_productions')
                    ->where(fn ($query) => $query
                        ->where('livestock_id', $targetLivestockId)
                        ->whereDate('date', $request->input('date')))
                    ->ignore($dailyProduction->id),
            ],
            'egg_count' => ['sometimes', 'integer', 'min:0'],
            'egg_weight_kg' => ['sometimes', 'numeric', 'min:0'],
            'feed_consumed_kg' => ['sometimes', 'numeric', 'min:0'],
            'water_consumed_liter' => ['sometimes', 'numeric', 'min:0'],
            'mortality' => ['sometimes', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($dailyProduction, $validated): void {
            $livestockIds = array_values(array_unique([
                $dailyProduction->livestock_id,
                $validated['livestock_id'] ?? $dailyProduction->livestock_id,
            ]));

            sort($livestockIds);

            $livestocks = Livestock::query()
                ->whereIn('id', $livestockIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $oldLivestock = $livestocks->get($dailyProduction->livestock_id);
            $newLivestock = $livestocks->get($validated['livestock_id'] ?? $dailyProduction->livestock_id);

            if (! $oldLivestock || ! $newLivestock) {
                throw ValidationException::withMessages([
                    'livestock_id' => 'Data populasi ayam tidak ditemukan.',
                ]);
            }

            $newMortality = $validated['mortality'] ?? $dailyProduction->mortality;

            if ($oldLivestock->is($newLivestock)) {
                $this->applyMortalityDelta($newLivestock, $newMortality - $dailyProduction->mortality);
            } else {
                $this->restorePopulation($oldLivestock, $dailyProduction->mortality);
                $this->decreasePopulation($newLivestock, $newMortality);
            }

            $dailyProduction->update($validated);
        });

        return response()->json($dailyProduction->fresh()->load('livestock.farm'));
    }

    /**
     * Remove the specified daily production record.
     */
    public function destroy(DailyProduction $dailyProduction): JsonResponse
    {
        Gate::authorize('delete', $dailyProduction);

        DB::transaction(function () use ($dailyProduction): void {
            $livestock = Livestock::query()
                ->lockForUpdate()
                ->find($dailyProduction->livestock_id);

            if ($livestock) {
                $this->restorePopulation($livestock, $dailyProduction->mortality);
            }

            $dailyProduction->delete();
        });

        return response()->json([
            'message' => 'Daily production deleted successfully.',
        ]);
    }

    /**
     * Reduce the live population by the recorded mortality.
     */
    private function decreasePopulation(Livestock $livestock, int $mortality): void
    {
        if ($mortality <= 0) {
            return;
        }

        if ($mortality > $livestock->current_population) {
            throw ValidationException::withMessages([
                'mortality' => "Mortalitas ({$mortality} ekor) melebihi populasi hidup saat ini ({$livestock->current_population} ekor).",
            ]);
        }

        $livestock->current_population -= $mortality;
        $livestock->save();
    }

    /**
     * Restore the live population when a mortality record is corrected or removed.
     */
    private function restorePopulation(Livestock $livestock, int $mortality): void
    {
        if ($mortality <= 0) {
            return;
        }

        $livestock->current_population = min(
            $livestock->initial_population,
            $livestock->current_population + $mortality,
        );
        $livestock->save();
    }

    /**
     * Apply a mortality change between two daily production records.
     */
    private function applyMortalityDelta(Livestock $livestock, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        if ($delta > 0) {
            $this->decreasePopulation($livestock, $delta);

            return;
        }

        $this->restorePopulation($livestock, abs($delta));
    }
}
