<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Notification;
use App\Models\SimulationRun;
use App\Models\SimulationScenario;
use Database\Factories\FactoryHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Mô phỏng kịch bản (thách thức nâng cao): sinh cảnh báo is_simulated, đối sánh, thông báo; kết thúc = hết hiệu lực. */
class SimulationTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    private function scenario(array $zoneCodes): SimulationScenario
    {
        FactoryHelpers::hazardTypeId('ap_thap');

        return SimulationScenario::create([
            'slug' => 'bao-thu', 'name' => 'Bão thử', 'hazard_type_id' => FactoryHelpers::hazardTypeId('bao'), 'severity' => 4,
            'zone_codes' => $zoneCodes, 'duration_hours' => 12, 'status' => 'ready',
            'payload' => ['name' => ['vi' => 'Bão thử', 'en' => 'Test typhoon'], 'source_name' => 'Kịch bản mô phỏng (kiểm thử)', 'steps' => [
                ['at_minute' => 0, 'severity' => 2, 'hazard_code' => 'ap_thap', 'zone_codes' => $zoneCodes,
                    'headline' => ['vi' => '[MÔ PHỎNG] Áp thấp', 'en' => '[SIMULATION] Depression']],
                ['at_minute' => 10, 'severity' => 4, 'hazard_code' => 'bao', 'zone_codes' => $zoneCodes,
                    'headline' => ['vi' => '[MÔ PHỎNG] Bão đổ bộ', 'en' => '[SIMULATION] Landfall']],
            ]],
        ]);
    }

    public function test_admin_run_creates_simulated_alerts_matches_and_notifications(): void
    {
        $zone = $this->zoneIn('KH', ['code' => 'khanh-hoa-test']);
        $trip = $this->tripInZone($zone);
        $scenario = $this->scenario([$zone->code]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/vi/quan-tri/mo-phong/chay', ['slug' => $scenario->slug, 'mode' => 'instant'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $run = SimulationRun::sole();
        $alerts = Alert::where('scenario_id', $scenario->id)->get();
        $this->assertCount(2, $alerts);
        $this->assertTrue($alerts->every(fn (Alert $a) => $a->is_simulated && $a->status === Alert::STATUS_ACTIVE));
        $this->assertSame(2, $run->alerts_created);
        $this->assertSame(2, $run->matches_created);
        $this->assertGreaterThan(0, $run->notifications_created);
        $this->assertSame($run->notifications_created, Notification::whereIn('alert_id', $alerts->pluck('id'))->count());
        $this->assertSame(4, $trip->fresh()->risk_level_cached);
        $this->assertSame('running', $scenario->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'simulation.run', 'subject_type' => 'run', 'subject_id' => $run->id]);

        $this->actingAs($admin)->get('/vi/quan-tri/mo-phong')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Scenarios/Index')->where('scenarios.0.slug', 'bao-thu')->where('scenarios.0.last_run.alerts_created', 2)
            ->where('runs.0.steps_done', 2));

        $this->actingAs($admin)->post("/vi/quan-tri/mo-phong/lan-chay/{$run->id}/ket-thuc")->assertSessionHasNoErrors();
        $this->assertSame([Alert::STATUS_EXPIRED], Alert::where('scenario_id', $scenario->id)->pluck('status')->unique()->values()->all());
        $this->assertNotNull($run->fresh()->finished_at);
        $this->assertSame(0, $trip->fresh()->risk_level_cached);
    }

    public function test_realtime_mode_queues_delayed_steps(): void
    {
        $zone = $this->zoneIn('KH', ['code' => 'khanh-hoa-test']);
        $scenario = $this->scenario([$zone->code]);
        config(['queue.default' => 'database']);

        $this->actingAs($this->admin())->post('/vi/quan-tri/mo-phong/chay', ['slug' => $scenario->slug, 'mode' => 'realtime']);

        $this->assertSame(1, Alert::where('scenario_id', $scenario->id)->count());
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(1, SimulationRun::sole()->summary['steps_done']);
    }
}
