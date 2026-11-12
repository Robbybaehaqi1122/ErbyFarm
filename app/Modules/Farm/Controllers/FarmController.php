<?php

namespace App\Modules\Farm\Controllers;

use App\Modules\Farm\Models\Farm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FarmController extends Controller
{
    /**
     * Display a listing of farms.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Farm::class);

        $search = $request->string('search')->trim()->toString();

        $farms = Farm::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status'));
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends(request()->query());

        return response()->json($farms);
    }

    /**
     * Store a newly created farm.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Farm::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:farms,name'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(Farm::statuses())],
            'notes' => ['nullable', 'string'],
        ]);

        $farm = Farm::create($validated);

        return response()->json($farm, 201);
    }

    /**
     * Display the specified farm.
     */
    public function show(Farm $farm): JsonResponse
    {
        Gate::authorize('view', $farm);

        return response()->json($farm);
    }

    /**
     * Update the specified farm.
     */
    public function update(Request $request, Farm $farm): JsonResponse
    {
        Gate::authorize('update', $farm);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('farms', 'name')->ignore($farm->id)],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(Farm::statuses())],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $farm->update($validated);

        return response()->json($farm);
    }

    /**
     * Remove the specified farm.
     */
    public function destroy(Farm $farm): JsonResponse
    {
        Gate::authorize('delete', $farm);

        $farm->delete();

        return response()->json([
            'message' => 'Farm deleted successfully.',
        ]);
    }
}
