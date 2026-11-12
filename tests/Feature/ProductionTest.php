<?php

namespace Tests\Feature;

use App\Modules\Farm\Models\Farm;
use App\Modules\Livestock\Models\Livestock;
use App\Modules\Production\Models\DailyProduction;
use App\Modules\User\Database\Seeders\RoleSeeder;
use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;

    protected Livestock $livestock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // Make Sanctum treat the request as a stateful same-origin SPA request.
        $this->withHeader('Origin', 'http://localhost');

        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);
        Sanctum::actingAs($user);

        $farm = Farm::create([
            'name' => 'Kandang A1',
            'capacity' => 6000,
            'status' => 'active',
        ]);

        $this->livestock = Livestock::create([
            'farm_id' => $farm->id,
            'initial_population' => 5000,
            'current_population' => 5000,
            'entry_date' => now()->subWeeks(4)->toDateString(),
            'age_weeks' => 4,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_daily_production_and_population_is_reduced(): void
    {
        $response = $this->postJson('/api/daily-productions', [
            'livestock_id' => $this->livestock->id,
            'date' => now()->toDateString(),
            'egg_count' => 4200,
            'egg_weight_kg' => 125.5,
            'feed_consumed_kg' => 480.25,
            'water_consumed_liter' => 900,
            'mortality' => 25,
        ]);

        $response->assertCreated()
            ->assertJsonPath('livestock_id', $this->livestock->id)
            ->assertJsonPath('egg_count', 4200)
            ->assertJsonPath('egg_weight_kg', '125.50')
            ->assertJsonPath('mortality', 25)
            ->assertJsonPath('livestock.farm.name', 'Kandang A1');

        $this->assertDatabaseHas('daily_productions', [
            'livestock_id' => $this->livestock->id,
            'egg_count' => 4200,
            'mortality' => 25,
        ]);

        $this->assertSame(4975, $this->livestock->fresh()->current_population);

        $this->getJson('/api/daily-productions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.livestock.farm.name', 'Kandang A1');
    }

    public function test_updating_mortality_adjusts_population_by_the_difference(): void
    {
        $production = $this->createProduction(mortality: 25);

        $this->assertSame(4975, $this->livestock->fresh()->current_population);

        $this->patchJson("/api/daily-productions/{$production->id}", [
            'mortality' => 50,
        ])->assertOk();

        $this->assertSame(4950, $this->livestock->fresh()->current_population);

        $this->patchJson("/api/daily-productions/{$production->id}", [
            'mortality' => 10,
        ])->assertOk();

        $this->assertSame(4990, $this->livestock->fresh()->current_population);
    }

    public function test_deleting_daily_production_restores_population(): void
    {
        $production = $this->createProduction(mortality: 40);

        $this->assertSame(4960, $this->livestock->fresh()->current_population);

        $this->deleteJson("/api/daily-productions/{$production->id}")->assertOk();

        $this->assertDatabaseMissing('daily_productions', ['id' => $production->id]);
        $this->assertSame(5000, $this->livestock->fresh()->current_population);
    }

    public function test_moving_a_record_to_another_flock_restores_the_old_population(): void
    {
        $otherLivestock = Livestock::create([
            'farm_id' => $this->livestock->farm_id,
            'initial_population' => 3000,
            'current_population' => 3000,
            'entry_date' => now()->subWeeks(2)->toDateString(),
            'age_weeks' => 2,
            'status' => 'active',
        ]);

        $production = $this->createProduction(mortality: 10);

        $this->assertSame(4990, $this->livestock->fresh()->current_population);

        $this->patchJson("/api/daily-productions/{$production->id}", [
            'livestock_id' => $otherLivestock->id,
            'mortality' => 20,
        ])->assertOk();

        $this->assertSame(5000, $this->livestock->fresh()->current_population);
        $this->assertSame(2980, $otherLivestock->fresh()->current_population);
    }

    public function test_mortality_cannot_exceed_current_population(): void
    {
        $this->postJson('/api/daily-productions', [
            'livestock_id' => $this->livestock->id,
            'date' => now()->toDateString(),
            'egg_count' => 100,
            'egg_weight_kg' => 5,
            'feed_consumed_kg' => 10,
            'water_consumed_liter' => 20,
            'mortality' => 5001,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mortality');

        $this->assertSame(5000, $this->livestock->fresh()->current_population);
    }

    public function test_a_flock_can_only_have_one_record_per_day(): void
    {
        $this->createProduction(mortality: 0);

        $this->postJson('/api/daily-productions', [
            'livestock_id' => $this->livestock->id,
            'date' => now()->toDateString(),
            'egg_count' => 100,
            'egg_weight_kg' => 5,
            'feed_consumed_kg' => 10,
            'water_consumed_liter' => 20,
            'mortality' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    private function createProduction(int $mortality): DailyProduction
    {
        $production = DailyProduction::create([
            'livestock_id' => $this->livestock->id,
            'date' => now()->toDateString(),
            'egg_count' => 4000,
            'egg_weight_kg' => 120,
            'feed_consumed_kg' => 450,
            'water_consumed_liter' => 850,
            'mortality' => $mortality,
        ]);

        if ($mortality > 0) {
            $this->livestock->decrement('current_population', $mortality);
        }

        return $production;
    }
}
