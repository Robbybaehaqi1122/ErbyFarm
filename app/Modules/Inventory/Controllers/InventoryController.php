<?php

namespace App\Modules\Inventory\Controllers;

use App\Modules\Inventory\Models\Inventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    /**
     * Display a listing of inventory items with warehouse summary.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Inventory::class);

        $search = $request->string('search')->trim()->toString();

        $items = Inventory::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('item_name', 'like', "%{$search}%");
            })
            ->when($request->filled('category'), function (Builder $query) use ($request): void {
                $query->where('category', $request->string('category')->toString());
            })
            ->when($request->filled('stock_status'), function (Builder $query) use ($request): void {
                match ($request->string('stock_status')->toString()) {
                    'low' => $query->where('stock_quantity', '>', 0)
                        ->whereColumn('stock_quantity', '<=', 'minimum_stock'),
                    'out' => $query->where('stock_quantity', '<=', 0),
                    'safe' => $query->whereColumn('stock_quantity', '>', 'minimum_stock'),
                    default => null,
                };
            })
            ->orderBy('item_name')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return response()->json(array_merge($items->toArray(), [
            'summary' => [
                'total_items' => Inventory::query()->count(),
                'low_stock_items' => Inventory::query()
                    ->where('stock_quantity', '>', 0)
                    ->whereColumn('stock_quantity', '<=', 'minimum_stock')
                    ->count(),
                'out_of_stock_items' => Inventory::query()
                    ->where('stock_quantity', '<=', 0)
                    ->count(),
                'category_count' => Inventory::query()->distinct()->count('category'),
                'by_category' => Inventory::query()
                    ->select('category')
                    ->selectRaw('COUNT(*) as item_count')
                    ->selectRaw('SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= minimum_stock THEN 1 ELSE 0 END) as low_stock_count')
                    ->selectRaw('SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock_count')
                    ->groupBy('category')
                    ->orderBy('category')
                    ->get()
                    ->map(fn ($row): array => [
                        'category' => (string) $row->category,
                        'item_count' => (int) $row->item_count,
                        'low_stock_count' => (int) $row->low_stock_count,
                        'out_of_stock_count' => (int) $row->out_of_stock_count,
                    ])
                    ->values()
                    ->all(),
            ],
        ]));
    }

    /**
     * Store a newly created inventory item.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Inventory::class);

        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(Inventory::categories())],
            'stock_quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
        ]);

        $inventory = Inventory::create($validated);

        return response()->json($inventory, 201);
    }

    /**
     * Display the specified inventory item.
     */
    public function show(Inventory $inventory): JsonResponse
    {
        Gate::authorize('view', $inventory);

        return response()->json($inventory);
    }

    /**
     * Update the specified inventory item.
     */
    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        Gate::authorize('update', $inventory);

        $validated = $request->validate([
            'item_name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', Rule::in(Inventory::categories())],
            'stock_quantity' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:30'],
            'minimum_stock' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $inventory->update($validated);

        return response()->json($inventory->fresh());
    }

    /**
     * Remove the specified inventory item.
     */
    public function destroy(Inventory $inventory): JsonResponse
    {
        Gate::authorize('delete', $inventory);

        $inventory->delete();

        return response()->json([
            'message' => 'Inventory item deleted successfully.',
        ]);
    }
}
