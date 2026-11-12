<?php

namespace Tests\Feature;

use App\Modules\Farm\Models\Farm;
use App\Modules\Health\Models\HealthRecord;
use App\Modules\Livestock\Models\Livestock;
use App\Modules\User\Database\Seeders\RoleSeeder;
use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    protected Livestock $livestockA;

    protected Livestock $livestockB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // Make Sanctum treat the request as a stateful same-origin SPA request.
        $this->withHeader('Origin', 'http://localhost');

        $user = User::factory()->create();
        $user->assignRole(Role::Admin->value);
        Sanctum::actingAs($user);

        $farmA = Farm::create([
            'name' => 'Kandang A1',
            'capacity' => 2000,
            'status' => 'active',
        ]);

        $farmB = Farm::create([
            'name' => 'Kandang B1',
            'capacity' => 2000,
            'status' => 'active',
        ]);

        $this->livestockA = Livestock::create([
            'farm_id' => $farmA->id,
            'initial_population' => 1000,
            'current_population' => 990,
            'entry_date' => '2026-10-01',
            'age_weeks' => 4,
            'status' => 'active',
        ]);

        $this->livestockB = Livestock::create([
            'farm_id' => $farmB->id,
            'initial_population' => 1000,
            'current_population' => 995,
            'entry_date' => '2026-10-01',
            'age_weeks' => 4,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_and_list_health_records_with_summary(): void
    {
        $this->postJson('/api/health-records', [
            'livestock_id' => $this->livestockA->id,
            'treatment_type' => 'vaksinasi',
            'medicine_name' => 'Vaksin ND',
            'dosage' => '0.5 ml/ekor',
            'date' => '2026-11-02',
            'notes' => 'Vaksinasi rutin minggu ke-4',
        ])
            ->assertCreated()
            ->assertJsonPath('treatment_type', 'vaksinasi')
            ->assertJsonPath('medicine_name', 'Vaksin ND')
            ->assertJsonPath('dosage', '0.5 ml/ekor')
            ->assertJsonPath('date', '2026-11-02')
            ->assertJsonPath('livestock.farm.name', 'Kandang A1');

        $this->createRecord($this->livestockA, 'pengobatan', 'Amoxicillin', '2026-11-03');
        $this->createRecord($this->livestockA, 'vitamin', 'Vitastress', '2026-11-04');
        $this->createRecord($this->livestockB, 'vaksinasi', 'Vaksin AI', '2026-11-05');
        $this->createRecord($this->livestockB, 'culling', null, '2026-11-06');

        $response = $this->getJson('/api/health-records?from=2026-11-01&to=2026-11-30');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.treatment_type', 'culling')
            ->assertJsonPath('summary.total_records', 5)
            ->assertJsonPath('summary.livestock_count', 2)
            ->assertJsonPath('summary.vaksinasi_count', 2)
            ->assertJsonPath('summary.pengobatan_count', 1)
            ->assertJsonPath('summary.vitamin_count', 1)
            ->assertJsonPath('summary.culling_count', 1);
    }

    public function test_health_records_can_be_filtered(): void
    {
        $this->createRecord($this->livestockA, 'vaksinasi', 'Vaksin ND', '2026-11-02');
        $this->createRecord($this->livestockA, 'pengobatan', 'Amoxicillin', '2026-11-03');
        $this->createRecord($this->livestockB, 'vaksinasi', 'Vaksin AI', '2026-11-05');
        $this->createRecord($this->livestockB, 'culling', null, '2026-11-06');

        $this->getJson("/api/health-records?livestock_id={$this->livestockA->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.total_records', 2);

        $this->getJson('/api/health-records?treatment_type=vaksinasi')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.vaksinasi_count', 2)
            ->assertJsonPath('summary.pengobatan_count', 0);

        $this->getJson('/api/health-records?search=Amoxicillin')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.medicine_name', 'Amoxicillin');

        $this->getJson('/api/health-records?search=Kandang B1')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/health-records?from=2026-11-03&to=2026-11-05')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_update_and_delete_a_health_record(): void
    {
        $record = $this->createRecord($this->livestockA, 'pengobatan', 'Amoxicillin', '2026-11-03');

        $this->patchJson("/api/health-records/{$record->id}", [
            'treatment_type' => 'vitamin',
            'medicine_name' => 'Vitastress',
            'dosage' => '1 ml/liter',
            'notes' => 'Ditingkatkan setelah cek kondisi',
        ])
            ->assertOk()
            ->assertJsonPath('treatment_type', 'vitamin')
            ->assertJsonPath('medicine_name', 'Vitastress')
            ->assertJsonPath('dosage', '1 ml/liter')
            ->assertJsonPath('notes', 'Ditingkatkan setelah cek kondisi');

        $this->deleteJson("/api/health-records/{$record->id}")->assertOk();

        $this->assertDatabaseMissing('health_records', ['id' => $record->id]);
    }

    public function test_operator_can_create_but_not_delete_and_viewer_is_read_only(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole(Role::Operator->value);
        Sanctum::actingAs($operator);

        $recordId = $this->postJson('/api/health-records', [
            'livestock_id' => $this->livestockA->id,
            'treatment_type' => 'vaksinasi',
            'medicine_name' => 'Vaksin ND',
            'dosage' => '0.5 ml/ekor',
            'date' => '2026-11-02',
        ])
            ->assertCreated()
            ->json('id');

        $this->deleteJson("/api/health-records/{$recordId}")->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->assignRole(Role::Viewer->value);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/health-records')->assertOk();

        $this->postJson('/api/health-records', [
            'livestock_id' => $this->livestockA->id,
            'treatment_type' => 'vitamin',
            'date' => '2026-11-03',
        ])->assertForbidden();
    }

    public function test_health_record_validation_rejects_invalid_data(): void
    {
        $this->postJson('/api/health-records', [
            'livestock_id' => 999999,
            'treatment_type' => 'tidak_ada',
            'date' => 'bukan-tanggal',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['livestock_id', 'treatment_type', 'date']);
    }

    private function createRecord(
        Livestock $livestock,
        string $type,
        ?string $medicine,
        string $date,
    ): HealthRecord {
        return HealthRecord::create([
            'livestock_id' => $livestock->id,
            'treatment_type' => $type,
            'medicine_name' => $medicine,
            'dosage' => $medicine ? 'Sesuai anjuran' : null,
            'date' => $date,
            'notes' => null,
        ]);
    }
}
