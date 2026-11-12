<?php

namespace Tests\Feature;

use App\Modules\Farm\Models\Farm;
use App\Modules\Livestock\Models\Livestock;
use App\Modules\User\Database\Seeders\RoleSeeder;
use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LivestockTest extends TestCase
{
    use RefreshDatabase;

    protected Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // Make Sanctum treat the request as a stateful same-origin SPA request.
        $this->withHeader('Origin', 'http://localhost');

        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);
        Sanctum::actingAs($user);

        $this->farm = Farm::create([
            'name' => 'Kandang A1',
            'capacity' => 6000,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_and_list_livestock(): void
    {
        $response = $this->postJson('/api/livestocks', [
            'farm_id' => $this->farm->id,
            'initial_population' => 5000,
            'entry_date' => now()->subWeeks(3)->toDateString(),
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('farm_id', $this->farm->id)
            ->assertJsonPath('initial_population', 5000)
            ->assertJsonPath('current_population', 5000)
            ->assertJsonPath('age_weeks', 3)
            ->assertJsonPath('farm.name', 'Kandang A1');

        $this->getJson('/api/livestocks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.farm.name', 'Kandang A1');
    }

    public function test_livestock_can_be_updated_and_deleted(): void
    {
        $livestock = Livestock::create([
            'farm_id' => $this->farm->id,
            'initial_population' => 5000,
            'current_population' => 5000,
            'entry_date' => now()->subWeeks(2)->toDateString(),
            'age_weeks' => 2,
            'status' => 'active',
        ]);

        $this->patchJson("/api/livestocks/{$livestock->id}", [
            'current_population' => 4800,
            'status' => 'culled',
        ])
            ->assertOk()
            ->assertJsonPath('current_population', 4800)
            ->assertJsonPath('status', 'culled');

        $this->deleteJson("/api/livestocks/{$livestock->id}")->assertOk();

        $this->assertDatabaseMissing('livestocks', ['id' => $livestock->id]);
    }

    public function test_deleting_a_farm_cascades_its_livestock(): void
    {
        $livestock = Livestock::create([
            'farm_id' => $this->farm->id,
            'initial_population' => 5000,
            'current_population' => 5000,
            'entry_date' => now()->toDateString(),
            'age_weeks' => 0,
            'status' => 'active',
        ]);

        $this->farm->delete();

        $this->assertDatabaseMissing('livestocks', ['id' => $livestock->id]);
    }
}
