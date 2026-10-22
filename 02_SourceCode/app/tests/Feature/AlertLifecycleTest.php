<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertMatch;
use App\Models\Notification;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Máy trạng thái cảnh báo: draft → active → updated → expired/cancelled; phát hành = đối sánh + xếp thông báo. */
class AlertLifecycleTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    private function alertPayload(array $zones, array $overrides = []): array
    {
        return array_merge([
            'hazard_code' => $this->hazardCode('bao'),
            'severity' => 3,
            'zone_codes' => collect($zones)->pluck('code')->all(),
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
            'source_name' => 'Trung tâm hỗ trợ du khách Khánh Hòa (mô phỏng)',
            'source_url' => 'https://example.test/canh-bao',
            'translations' => ['vi' => ['headline' => 'Bão gần bờ', 'description' => 'Gió mạnh'], 'en' => ['headline' => 'Typhoon near the coast']],
        ], $overrides);
    }

    public function test_agency_creates_publishes_updates_and_expires_alert(): void
    {
        $agency = $this->agency('KH');
        $zone = $this->zoneIn('KH');
        $trip = $this->tripInZone($zone);

        $this->actingAs($agency)->post('/vi/quan-tri/canh-bao/tao', $this->alertPayload([$zone]))->assertSessionHasNoErrors();
        $alert = Alert::sole();
        $this->assertSame(Alert::STATUS_DRAFT, $alert->status);
        $this->assertSame(0, AlertMatch::count());

        $this->actingAs($agency)->post("/vi/quan-tri/canh-bao/{$alert->code}/trang-thai", ['status' => 'active'])->assertSessionHasNoErrors();
        $alert->refresh();
        $this->assertSame(Alert::STATUS_ACTIVE, $alert->status);
        $this->assertNotNull($alert->published_at);
        $this->assertSame(1, AlertMatch::where('trip_id', $trip->id)->count());
        $this->assertSame(AlertMatch::STATUS_NOTIFIED, AlertMatch::first()->status);
        // Mức 3 mặc định: in-app + email cho du khách
        $this->assertEqualsCanonicalizing(['inapp', 'email'], Notification::where('alert_id', $alert->id)->pluck('channel')->all());

        $this->actingAs($agency)->put("/vi/quan-tri/canh-bao/{$alert->code}/sua", $this->alertPayload([$zone], ['severity' => 4]))->assertSessionHasNoErrors();
        $this->assertSame(Alert::STATUS_UPDATED, $alert->fresh()->status);
        $this->assertSame(4, $trip->fresh()->risk_level_cached);

        $this->actingAs($agency)->post("/vi/quan-tri/canh-bao/{$alert->code}/trang-thai", ['status' => 'expired'])->assertSessionHasNoErrors();
        $this->assertSame(Alert::STATUS_EXPIRED, $alert->fresh()->status);
        $this->assertSame(0, Notification::where('alert_id', $alert->id)->where('status', 'queued')->count());
        $this->assertSame(['cancelled'], Notification::where('alert_id', $alert->id)->pluck('status')->unique()->values()->all());
        $this->assertSame(AlertMatch::STATUS_RESOLVED, AlertMatch::first()->status);
        $this->assertSame(0, $trip->fresh()->risk_level_cached);
        foreach (['alert.create', 'alert.publish', 'alert.update', 'alert.expire'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'subject_type' => 'alert', 'subject_id' => $alert->id]);
        }
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $admin = $this->admin();
        $expired = $this->alertForZones([$this->zoneIn()], 2, Alert::STATUS_EXPIRED);
        $draft = $this->alertForZones([$this->zoneIn()], 2, Alert::STATUS_DRAFT);

        $this->actingAs($admin)->from('/vi/quan-tri')->post("/vi/quan-tri/canh-bao/{$expired->code}/trang-thai", ['status' => 'active'])
            ->assertRedirect('/vi/quan-tri')->assertSessionHasErrors('status');
        $this->actingAs($admin)->post("/vi/quan-tri/canh-bao/{$draft->code}/trang-thai", ['status' => 'expired'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->post("/vi/quan-tri/canh-bao/{$draft->code}/trang-thai", ['status' => 'draft'])->assertSessionHasErrors('status');
        $this->assertSame(Alert::STATUS_EXPIRED, $expired->fresh()->status);
        $this->assertSame(Alert::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertFalse($expired->fresh()->canTransitionTo('active'));
    }

    public function test_alert_form_validates_input(): void
    {
        $agency = $this->agency('KH');
        $this->actingAs($agency)->post('/vi/quan-tri/canh-bao/tao', [
            'hazard_code' => 'khong-co', 'severity' => 7, 'source_name' => '', 'source_url' => 'javascript:alert(1)',
            'geometry' => '{"type":"Circle","center":{"lat":12,"lng":109},"radius_km":9000}', 'translations' => ['vi' => ['headline' => '']],
            'starts_at' => now()->toDateTimeString(), 'ends_at' => now()->subDay()->toDateTimeString(),
        ])->assertSessionHasErrors(['hazard_code', 'severity', 'source_name', 'source_url', 'translations.vi.headline', 'ends_at']);
        $this->assertSame(0, Alert::count());
    }

    public function test_agency_cannot_edit_alert_of_other_province(): void
    {
        $alert = $this->alertForZones([$this->zoneIn('HN')], 2, Alert::STATUS_DRAFT);

        $this->actingAs($this->agency('KH'))->post("/vi/quan-tri/canh-bao/{$alert->code}/trang-thai", ['status' => 'active'])->assertForbidden();
        $this->assertSame(Alert::STATUS_DRAFT, $alert->fresh()->status);
    }

    public function test_expire_due_closes_live_alerts_past_end(): void
    {
        $old = $this->alertForZones([$this->zoneIn()], 2, Alert::STATUS_ACTIVE, ['ends_at' => now()->subMinute()]);
        $current = $this->alertForZones([$this->zoneIn()], 2);

        $this->artisan('app:expire-alerts')->assertSuccessful();

        $this->assertSame(Alert::STATUS_EXPIRED, $old->fresh()->status);
        $this->assertSame(Alert::STATUS_ACTIVE, $current->fresh()->status);
        $this->assertSame(0, app(AlertService::class)->expireDue());
    }

    public function test_public_alert_pages_hide_drafts_and_show_source(): void
    {
        $zone = $this->zoneIn();
        $live = $this->alertForZones([$zone], 4);
        $draft = $this->alertForZones([$zone], 4, Alert::STATUS_DRAFT);

        $this->get('/vi/canh-bao')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Alerts/Index')
            ->has('alerts.data', 1)->where('alerts.data.0.code', $live->code)
            ->where('alerts.data.0.source.name', 'Nguồn kiểm thử')->has('alerts.data.0.updated_at_source')
            ->where('filters.status', 'active')->has('hazards')->has('provinces'));
        $this->get("/vi/canh-bao/{$live->code}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Alerts/Show')
            ->where('alert.code', $live->code)->has('alert.guides')->has('alert.history')->missing('alert.affected_trips_count')
            ->where('zonesGeo.features.0.properties.level', 4));
        $this->get("/vi/canh-bao/{$draft->code}")->assertNotFound();
    }
}
