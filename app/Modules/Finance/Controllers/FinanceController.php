<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Farm\Models\Farm;
use App\Modules\Finance\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    /**
     * Display a listing of expenses with a filtered summary.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Expense::class);

        $query = $this->filteredQuery($request);

        $expenses = (clone $query)
            ->with('farm:id,name')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        $byCategory = (clone $query)
            ->select('category')
            ->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('COUNT(*) as total_records')
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($row): array => [
                'category' => (string) $row->category,
                'total_amount' => round((float) $row->total_amount, 2),
                'total_records' => (int) $row->total_records,
            ])
            ->values()
            ->all();

        $totalAmount = (float) (clone $query)->sum('amount');
        $totalRecords = (int) (clone $query)->count();

        return response()->json(array_merge($expenses->toArray(), [
            'summary' => [
                'total_amount' => round($totalAmount, 2),
                'total_records' => $totalRecords,
                'average_amount' => $totalRecords > 0 ? round($totalAmount / $totalRecords, 2) : 0.0,
                'by_category' => $byCategory,
            ],
        ]));
    }

    /**
     * Store a newly created expense.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Expense::class);

        $validated = $request->validate([
            'farm_id' => ['nullable', 'integer', Rule::exists(Farm::class, 'id')],
            'category' => ['required', Rule::in(Expense::categories())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $expense = Expense::create($validated);

        return response()->json($expense->load('farm:id,name'), 201);
    }

    /**
     * Display the specified expense.
     */
    public function show(Expense $expense): JsonResponse
    {
        Gate::authorize('view', $expense);

        return response()->json($expense->load('farm:id,name'));
    }

    /**
     * Update the specified expense.
     */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        Gate::authorize('update', $expense);

        $validated = $request->validate([
            'farm_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Farm::class, 'id')],
            'category' => ['sometimes', Rule::in(Expense::categories())],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'date' => ['sometimes', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $expense->update($validated);

        return response()->json($expense->fresh()->load('farm:id,name'));
    }

    /**
     * Remove the specified expense.
     */
    public function destroy(Expense $expense): JsonResponse
    {
        Gate::authorize('delete', $expense);

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully.',
        ]);
    }

    /**
     * Apply the shared list filters used by the table and summary.
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('search')->trim()->toString();

        return Expense::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhereHas('farm', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('category'), function (Builder $query) use ($request): void {
                $query->where('category', $request->string('category')->toString());
            })
            ->when($request->filled('farm_id'), function (Builder $query) use ($request): void {
                if ($request->string('farm_id')->toString() === 'general') {
                    $query->whereNull('farm_id');

                    return;
                }

                $query->where('farm_id', $request->integer('farm_id'));
            })
            ->when($request->filled('from'), function (Builder $query) use ($request): void {
                $query->whereDate('date', '>=', $request->string('from')->toString());
            })
            ->when($request->filled('to'), function (Builder $query) use ($request): void {
                $query->whereDate('date', '<=', $request->string('to')->toString());
            });
    }
}
