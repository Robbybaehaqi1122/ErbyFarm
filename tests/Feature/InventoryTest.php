<?php

namespace Tests\Feature;

use App\Modules\Inventory\Models\Inventory;
use App\Modules\User\Database\Seeders\RoleSeeder;
use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // Make Sanctum treat the request as a stateful same-origin SPA request.
        $this->withHeader('Origin', 'http://localhost');

        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);
        Sanctum::actingAs($user);
    }

    public function test_admin_can_create_inventory_and_list_with_summary(): void
    {
        $this->postJson('/api/inventories', [
            'item_name' => 'Pakan Layer BR-1',
            'category' => 'pakan',
            'stock_quantity' => 25,
            'unit' => 'sak',
            'minimum_stock' => 10,
        ])
            ->assertCreated()
            ->assertJsonPath('item_name', 'Pakan Layer BR-1')
            ->assertJsonPath('category', 'pakan')
            ->assertJsonPath('stock_quantity', '25.00')
            ->assertJsonPath('unit', 'sak')
            ->assertJsonPath('minimum_stock', '10.00');

        $this->createItem('Pakan Starter', 'pakan', 5, 'sak', 10);
        $this->createItem('Vaksin ND', 'obat', 0, 'botol', 2);
        $this->createItem('Vitamin Anti-Stress', 'vitamin', 12, 'botol', 5);
        $this->createItem('Sarung Tangan', 'perlengkapan', 3, 'pack', 3);

        $response = $this->getJson('/api/inventories');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.item_name', 'Pakan Layer BR-1')
            ->assertJsonPath('summary.total_items', 5)
            ->assertJsonPath('summary.low_stock_items', 2)
            ->assertJsonPath('summary.out_of_stock_items', 1)
            ->assertJsonPath('summary.category_count', 4);

        $pakan = collect($response->json('summary.by_category'))->firstWhere('category', 'pakan');

        $this->assertNotNull($pakan);
        $this->assertSame(2, $pakan['item_count']);
        $this->assertSame(1, $pakan['low_stock_count']);
        $this->assertSame(0, $pakan['out_of_stock_count']);
    }

    public function test_inventory_can_be_filtered_by_category_search_and_stock_status(): void
    {
        $this->createItem('Pakan Layer BR-1', 'pakan', 25, 'sak', 10);
        $this->createItem('Pakan Starter', 'pakan', 5, 'sak', 10);
        $this->createItem('Vaksin ND', 'obat', 0, 'botol', 2);
        $this->createItem('Vitamin Anti-Stress', 'vitamin', 12, 'botol', 5);

        $this->getJson('/api/inventories?category=pakan')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/inventories?search=Vaksin')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'Vaksin ND');

        $this->getJson('/api/inventories?stock_status=low')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'Pakan Starter');

        $this->getJson('/api/inventories?stock_status=out')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'Vaksin ND');

        $this->getJson('/api/inventories?stock_status=safe')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_update_and_delete_an_inventory_item(): void
    {
        $inventory = $this->createItem('Pakan Layer BR-1', 'pakan', 25, 'sak', 10);

        $this->patchJson("/api/inventories/{$inventory->id}", [
            'stock_quantity' => 8,
            'minimum_stock' => 12,
            'unit' => 'kg',
        ])
            ->assertOk()
            ->assertJsonPath('stock_quantity', '8.00')
            ->assertJsonPath('minimum_stock', '12.00')
            ->assertJsonPath('unit', 'kg');

        $this->deleteJson("/api/inventories/{$inventory->id}")->assertOk();

        $this->assertDatabaseMissing('inventories', ['id' => $inventory->id]);
    }

    public function test_operator_can_create_but_not_delete_and_viewer_is_read_only(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole(Role::Operator->value);
        Sanctum::actingAs($operator);

        $inventoryId = $this->postJson('/api/inventories', [
            'item_name' => 'Pakan Layer BR-1',
            'category' => 'pakan',
            'stock_quantity' => 25,
            'unit' => 'sak',
            'minimum_stock' => 10,
        ])
            ->assertCreated()
            ->json('id');

        $this->deleteJson("/api/inventories/{$inventoryId}")->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->assignRole(Role::Viewer->value);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/inventories')->assertOk();

        $this->postJson('/api/inventories', [
            'item_name' => 'Vaksin ND',
            'category' => 'obat',
            'stock_quantity' => 5,
            'unit' => 'botol',
            'minimum_stock' => 2,
        ])->assertForbidden();
    }

    public function test_inventory_validation_rejects_invalid_data(): void
    {
        $this->postJson('/api/inventories', [
            'item_name' => '',
            'category' => 'tidak_ada',
            'stock_quantity' => -1,
            'unit' => '',
            'minimum_stock' => -5,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'item_name',
                'category',
                'stock_quantity',
                'unit',
                'minimum_stock',
            ]);
    }

    private function createItem(
        string $name,
        string $category,
        float $stock,
        string $unit,
        float $minimumStock,
    ): Inventory {
        return Inventory::create([
            'item_name' => $name,
            'category' => $category,
            'stock_quantity' => $stock,
            'unit' => $unit,
            'minimum_stock' => $minimumStock,
        ]);
    }
}
