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

class ReportTest extends TestCase
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

        $livestockA = Livestock::create([
            'farm_id' => $this->farmA->id,
            'initial_population' => 1000,
            'current_population' => 990,
            'entry_date' => '2026-10-01',
            'age_weeks' => 4,
            'status' => 'active',
        ]);

        $livestockB = Livestock::create([
            'farm_id' => $this->farmB->id,
            'initial_population' => 1000,
            'current_population' => 995,
            'entry_date' => '2026-10-01',
            'age_weeks' => 4,
            'status' => 'active',
        ]);

        $this->createProduction($livestockA, '2026-11-01', 900, 55, 100, 200, 0);
        $this->createProduction($livestockA, '2026-11-02', 800, 50, 110, 210, 10);
        $this->createProduction($livestockB, '2026-11-01', 500, 30, 60, 110, 5);
    }

    public function test_report_returns_summary_farm_recap_and_daily_periods(): void
    {
        $response = $this->getJson('/api/reports?from=2026-11-01&to=2026-11-02&group_by=day');

        $response->assertOk()
            ->assertJsonPath('meta.from', '2026-11-01')
            ->assertJsonPath('meta.to', '2026-11-02')
            ->assertJsonPath('meta.group_by', 'day')
            ->assertJsonPath('summary.total_eggs', 2200)
            ->assertJsonPath('summary.total_mortality', 15)
            ->assertJsonPath('summary.recorded_days', 2)
            ->assertJsonPath('summary.farm_count', 2)
            ->assertJsonCount(2, 'by_farm')
            ->assertJsonCount(2, 'periods');

        $summary = $response->json('summary');

        $this->assertEqualsWithDelta(270.0, $summary['total_feed_kg'], 0.001);
        $this->assertEqualsWithDelta(135.0, $summary['average_feed_kg'], 0.001);
        $this->assertEqualsWithDelta(73.33, $summary['hen_day'], 0.01);
        $this->assertEqualsWithDelta(61.4, $summary['average_egg_weight_gram'], 0.01);

        $farmA = collect($response->json('by_farm'))->firstWhere('farm_id', $this->farmA->id);

        $this->assertNotNull($farmA);
        $this->assertSame(1700, $farmA['total_eggs']);
        $this->assertSame(10, $farmA['total_mortality']);
        $this->assertEqualsWithDelta(85.0, $farmA['hen_day'], 0.01);
        $this->assertEqualsWithDelta(105.0, $farmA['average_feed_kg'], 0.001);

        $this->assertSame('2026-11-01', $response->json('periods.0.period'));
        $this->assertSame(1400, $response->json('periods.0.total_eggs'));
        $this->assertSame('2026-11-02', $response->json('periods.1.period'));
        $this->assertSame(800, $response->json('periods.1.total_eggs'));
    }

    public function test_report_can_filter_by_farm(): void
    {
        $response = $this->getJson("/api/reports?from=2026-11-01&to=2026-11-02&farm_id={$this->farmA->id}");

        $response->assertOk()
            ->assertJsonPath('meta.farm_id', $this->farmA->id)
            ->assertJsonPath('summary.total_eggs', 1700)
            ->assertJsonPath('summary.farm_count', 1)
            ->assertJsonCount(1, 'by_farm')
            ->assertJsonPath('by_farm.0.farm_name', 'Kandang A1');
    }

    public function test_report_can_group_periods_by_month(): void
    {
        $response = $this->getJson('/api/reports?from=2026-11-01&to=2026-11-30&group_by=month');

        $response->assertOk()
            ->assertJsonPath('meta.group_by', 'month')
            ->assertJsonCount(1, 'periods')
            ->assertJsonPath('periods.0.period', '2026-11')
            ->assertJsonPath('periods.0.total_eggs', 2200);
    }

    public function test_report_rejects_an_invalid_date_range(): void
    {
        $this->getJson('/api/reports?from=2026-11-10&to=2026-11-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    public function test_viewer_can_view_reports(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(Role::Viewer->value);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/reports')->assertOk();
    }

    private function createProduction(
        Livestock $livestock,
        string $date,
        int $eggCount,
        float $eggWeight,
        float $feed,
        float $water,
        int $mortality,
    ): DailyProduction {
        return DailyProduction::create([
            'livestock_id' => $livestock->id,
            'date' => $date,
            'egg_count' => $eggCount,
            'egg_weight_kg' => $eggWeight,
            'feed_consumed_kg' => $feed,
            'water_consumed_liter' => $water,
            'mortality' => $mortality,
        ]);
    }
}
