<?php

namespace Tests\Feature;

use App\Modules\Farm\Models\Farm;
use App\Modules\Finance\Models\Expense;
use App\Modules\User\Database\Seeders\RoleSeeder;
use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected Farm $farmA;

    protected Farm $farmB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // Make Sanctum treat the request as a stateful same-origin SPA request.
        $this->withHeader('Origin', 'http://localhost');

        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);
        Sanctum::actingAs($user);

        $this->farmA = Farm::create([
            'name' => 'Kandang A1',
            'capacity' => 2000,
            'status' => 'active',
        ]);

        $this->farmB = Farm::create([
            'name' => 'Kandang B1',
            'capacity' => 2000,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_and_list_expenses_with_summary(): void
    {
        $this->postJson('/api/expenses', [
            'farm_id' => $this->farmA->id,
            'category' => 'pakan',
            'amount' => 1500000,
            'date' => '2026-11-01',
            'description' => 'Pembelian pakan layer',
        ])
            ->assertCreated()
            ->assertJsonPath('category', 'pakan')
            ->assertJsonPath('amount', '1500000.00')
            ->assertJsonPath('date', '2026-11-01')
            ->assertJsonPath('farm.name', 'Kandang A1');

        $this->createExpense($this->farmA, 'pakan', 250000, '2026-11-02', 'Pakan tambahan');
        $this->createExpense(null, 'operasional', 500000, '2026-11-02', 'Listrik dan air');

        $response = $this->getJson('/api/expenses?from=2026-11-01&to=2026-11-30');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.description', 'Listrik dan air')
            ->assertJsonPath('summary.total_records', 3);

        $summary = $response->json('summary');

        $this->assertEqualsWithDelta(2250000.0, $summary['total_amount'], 0.001);
        $this->assertEqualsWithDelta(750000.0, $summary['average_amount'], 0.001);

        $pakan = collect($summary['by_category'])->firstWhere('category', 'pakan');

        $this->assertNotNull($pakan);
        $this->assertSame(2, $pakan['total_records']);
        $this->assertEqualsWithDelta(1750000.0, $pakan['total_amount'], 0.001);
    }

    public function test_expenses_can_be_filtered_by_category_farm_and_general(): void
    {
        $this->createExpense($this->farmA, 'pakan', 1000000, '2026-11-01', 'Pakan A');
        $this->createExpense($this->farmB, 'obat_vaksin', 300000, '2026-11-03', 'Vaksin B');
        $this->createExpense(null, 'gaji', 2000000, '2026-11-05', 'Gaji karyawan');

        $this->getJson('/api/expenses?category=pakan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Pakan A');

        $this->getJson("/api/expenses?farm_id={$this->farmA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.farm_id', $this->farmA->id);

        $this->getJson('/api/expenses?farm_id=general')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.farm_id', null)
            ->assertJsonPath('summary.total_records', 1);

        $this->getJson('/api/expenses?from=2026-11-02&to=2026-11-04')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Vaksin B');
    }

    public function test_admin_can_update_and_delete_an_expense(): void
    {
        $expense = $this->createExpense($this->farmA, 'pakan', 1000000, '2026-11-01', 'Pakan A');

        $this->patchJson("/api/expenses/{$expense->id}", [
            'category' => 'operasional',
            'amount' => 1250000,
            'farm_id' => $this->farmB->id,
            'description' => 'Diperbarui',
        ])
            ->assertOk()
            ->assertJsonPath('category', 'operasional')
            ->assertJsonPath('amount', '1250000.00')
            ->assertJsonPath('farm.name', 'Kandang B1')
            ->assertJsonPath('description', 'Diperbarui');

        $this->deleteJson("/api/expenses/{$expense->id}")->assertOk();

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_operator_can_create_but_not_delete_and_viewer_is_read_only(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole(Role::Operator->value);
        Sanctum::actingAs($operator);

        $expenseId = $this->postJson('/api/expenses', [
            'farm_id' => $this->farmA->id,
            'category' => 'pakan',
            'amount' => 500000,
            'date' => '2026-11-01',
            'description' => 'Pakan operator',
        ])
            ->assertCreated()
            ->json('id');

        $this->deleteJson("/api/expenses/{$expenseId}")->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->assignRole(Role::Viewer->value);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/expenses')->assertOk();

        $this->postJson('/api/expenses', [
            'category' => 'pakan',
            'amount' => 100000,
            'date' => '2026-11-02',
        ])->assertForbidden();
    }

    public function test_expense_validation_rejects_invalid_data(): void
    {
        $this->postJson('/api/expenses', [
            'farm_id' => 999999,
            'category' => 'tidak_ada',
            'amount' => 0,
            'date' => '2026-11-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['farm_id', 'category', 'amount']);
    }

    private function createExpense(
        ?Farm $farm,
        string $category,
        float $amount,
        string $date,
        string $description,
    ): Expense {
        return Expense::create([
            'farm_id' => $farm?->id,
            'category' => $category,
            'amount' => $amount,
            'date' => $date,
            'description' => $description,
        ]);
    }
}
