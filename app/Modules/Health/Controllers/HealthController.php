<?php

namespace App\Modules\Health\Controllers;

use App\Modules\Health\Models\HealthRecord;
use App\Modules\Livestock\Models\Livestock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class HealthController extends Controller
{
    /**
     * Display a listing of health and treatment records.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', HealthRecord::class);

        $query = $this->filteredQuery($request);

        $records = (clone $query)
            ->with([
                'livestock:id,farm_id,entry_date,current_population,status',
                'livestock.farm:id,name',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        $byType = (clone $query)
            ->select('treatment_type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('treatment_type')
            ->pluck('total', 'treatment_type');

        $totalRecords = (int) $byType->sum();

        return response()->json(array_merge($records->toArray(), [
            'summary' => [
                'total_records' => $totalRecords,
                'livestock_count' => (int) (clone $query)->distinct()->count('livestock_id'),
                'vaksinasi_count' => (int) ($byType['vaksinasi'] ?? 0),
                'pengobatan_count' => (int) ($byType['pengobatan'] ?? 0),
                'vitamin_count' => (int) ($byType['vitamin'] ?? 0),
                'culling_count' => (int) ($byType['culling'] ?? 0),
            ],
        ]));
    }

    /**
     * Store a newly created health record.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', HealthRecord::class);

        $validated = $request->validate([
            'livestock_id' => ['required', 'integer', Rule::exists(Livestock::class, 'id')],
            'treatment_type' => ['required', Rule::in(HealthRecord::treatmentTypes())],
            'medicine_name' => ['nullable', 'string', 'max:255'],
            'dosage' => ['nullable', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = HealthRecord::create($validated);

        return response()->json($record->load('livestock.farm'), 201);
    }

    /**
     * Display the specified health record.
     */
    public function show(HealthRecord $healthRecord): JsonResponse
    {
        Gate::authorize('view', $healthRecord);

        return response()->json($healthRecord->load('livestock.farm'));
    }

    /**
     * Update the specified health record.
     */
    public function update(Request $request, HealthRecord $healthRecord): JsonResponse
    {
        Gate::authorize('update', $healthRecord);

        $validated = $request->validate([
            'livestock_id' => ['sometimes', 'integer', Rule::exists(Livestock::class, 'id')],
            'treatment_type' => ['sometimes', Rule::in(HealthRecord::treatmentTypes())],
            'medicine_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dosage' => ['sometimes', 'nullable', 'string', 'max:100'],
            'date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $healthRecord->update($validated);

        return response()->json($healthRecord->fresh()->load('livestock.farm'));
    }

    /**
     * Remove the specified health record.
     */
    public function destroy(HealthRecord $healthRecord): JsonResponse
    {
        Gate::authorize('delete', $healthRecord);

        $healthRecord->delete();

        return response()->json([
            'message' => 'Health record deleted successfully.',
        ]);
    }

    /**
     * Apply the shared list filters used by the table and summary.
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('search')->trim()->toString();

        return HealthRecord::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('medicine_name', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('livestock.farm', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('livestock_id'), function (Builder $query) use ($request): void {
                $query->where('livestock_id', $request->integer('livestock_id'));
            })
            ->when($request->filled('treatment_type'), function (Builder $query) use ($request): void {
                $query->where('treatment_type', $request->string('treatment_type')->toString());
            })
            ->when($request->filled('from'), function (Builder $query) use ($request): void {
                $query->whereDate('date', '>=', $request->string('from')->toString());
            })
            ->when($request->filled('to'), function (Builder $query) use ($request): void {
                $query->whereDate('date', '<=', $request->string('to')->toString());
            });
    }
}
