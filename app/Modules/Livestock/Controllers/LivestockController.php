<?php

namespace App\Modules\Livestock\Controllers;

use App\Modules\Farm\Models\Farm;
use App\Modules\Livestock\Models\Livestock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LivestockController extends Controller
{
    /**
     * Display a listing of livestock (flock) records.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Livestock::class);

        $search = $request->string('search')->trim()->toString();

        $livestocks = Livestock::query()
            ->with('farm:id,name,capacity,status')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('farm', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('farm_id'), function ($query) use ($request): void {
                $query->where('farm_id', $request->integer('farm_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status'));
            })
            ->latest('entry_date')
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends(request()->query());

        return response()->json($livestocks);
    }

    /**
     * Store a newly created livestock record.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Livestock::class);

        $validated = $request->validate([
            'farm_id' => ['required', 'integer', Rule::exists(Farm::class, 'id')],
            'initial_population' => ['required', 'integer', 'min:1'],
            'current_population' => ['nullable', 'integer', 'min:0'],
            'entry_date' => ['required', 'date'],
            'age_weeks' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(Livestock::statuses())],
        ]);

        $validated['current_population'] ??= $validated['initial_population'];
        $validated['age_weeks'] ??= $this->ageInWeeks($validated['entry_date']);

        $livestock = Livestock::create($validated);

        return response()->json($livestock->load('farm:id,name,capacity,status'), 201);
    }

    /**
     * Display the specified livestock record.
     */
    public function show(Livestock $livestock): JsonResponse
    {
        Gate::authorize('view', $livestock);

        return response()->json($livestock->load('farm:id,name,capacity,status'));
    }

    /**
     * Update the specified livestock record.
     */
    public function update(Request $request, Livestock $livestock): JsonResponse
    {
        Gate::authorize('update', $livestock);

        $validated = $request->validate([
            'farm_id' => ['sometimes', 'integer', Rule::exists(Farm::class, 'id')],
            'initial_population' => ['sometimes', 'integer', 'min:1'],
            'current_population' => ['sometimes', 'integer', 'min:0'],
            'entry_date' => ['sometimes', 'date'],
            'age_weeks' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(Livestock::statuses())],
        ]);

        if (array_key_exists('entry_date', $validated) && ! array_key_exists('age_weeks', $validated)) {
            $validated['age_weeks'] = $this->ageInWeeks($validated['entry_date']);
        }

        $livestock->update($validated);

        return response()->json($livestock->load('farm:id,name,capacity,status'));
    }

    /**
     * Remove the specified livestock record.
     */
    public function destroy(Livestock $livestock): JsonResponse
    {
        Gate::authorize('delete', $livestock);

        $livestock->delete();

        return response()->json([
            'message' => 'Livestock deleted successfully.',
        ]);
    }

    /**
     * Calculate the age in weeks from the entry date.
     */
    private function ageInWeeks(string $entryDate): int
    {
        return max(0, (int) Carbon::parse($entryDate)->startOfDay()->diffInWeeks(now()->startOfDay()));
    }
}
