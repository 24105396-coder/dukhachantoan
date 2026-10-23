<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertMatch;
use App\Services\AnalyticsClient;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** BUG-1: hình học cảnh báo (Circle/Polygon) gửi qua form quản trị phải được lưu đủ và dùng để đối sánh. */
class AlertGeometryFormTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    private function pythonDown(): void
    {
        $mock = Mockery::mock(AnalyticsClient::class)->makePartial();
        $mock->shouldReceive('match')->andReturnNull();
        $mock->shouldReceive('isUp')->andReturnFalse();
        $this->app->instance(AnalyticsClient::class, $mock);
    }

    private function payload(array $zones, mixed $geometry, array $overrides = []): array
    {
        return array_merge([
            'hazard_code' => $this->hazardCode('bao'),
            'severity' => 3,
            'zone_codes' => collect($zones)->pluck('code')->all(),
            'geometry' => $geometry,
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
            'source_name' => 'Trung tâm hỗ trợ du khách Khánh Hòa (mô phỏng)',
            'translations' => ['vi' => ['headline' => 'Bão gần Nha Trang']],
        ], $overrides);
    }

    public function test_circle_geometry_from_admin_form_is_stored_and_matched(): void
    {
        $this->pythonDown();
        $zone = $this->zoneIn('KH', ['centroid_lat' => 12.24, 'centroid_lng' => 109.19]);
        $near = $this->tripInZone($zone, null, ['lat' => 12.30, 'lng' => 109.20]);
        $far = $this->tripInZone($zone, null, ['lat' => 13.50, 'lng' => 109.20]);
        $geometry = json_encode(['type' => 'Circle', 'center' => ['lat' => 12.2388, 'lng' => 109.1967], 'radius_km' => 25]);

        $this->actingAs($this->agency('KH'))->post('/vi/quan-tri/canh-bao/tao', $this->payload([$zone], $geometry, ['intent' => 'publish']))
            ->assertSessionHasNoErrors();

        $alert = Alert::sole();
        $this->assertSame(Alert::STATUS_ACTIVE, $alert->status);
        $this->assertSame('Circle', $alert->geometry['type']);
        $this->assertEqualsWithDelta(12.2388, (float) $alert->geometry['center']['lat'], 1e-6);
        $this->assertEqualsWithDelta(109.1967, (float) $alert->geometry['center']['lng'], 1e-6);
        $this->assertEqualsWithDelta(25.0, (float) $alert->geometry['radius_km'], 1e-6);

        // Phát hành đã đối sánh (đối sánh chuyển sang notified); gọi lại phải idempotent và cùng kết quả
        app(MatchingService::class)->matchAlert($alert);
        $matches = AlertMatch::query()->where('alert_id', $alert->id)->get();
        $this->assertSame([$near->id], $matches->pluck('trip_id')->all());
        $this->assertSame('geometry', $matches->first()->match_type);
        $this->assertNotNull($matches->first()->distance_km);
        $this->assertSame(0, $far->fresh()->risk_level_cached);
    }

    public function test_circle_geometry_as_array_is_kept_on_update(): void
    {
        $agency = $this->agency('KH');
        $zone = $this->zoneIn('KH');
        $this->actingAs($agency)->post('/vi/quan-tri/canh-bao/tao', $this->payload([$zone], null))->assertSessionHasNoErrors();
        $alert = Alert::sole();
        $geometry = ['type' => 'Circle', 'center' => ['lat' => 12.1, 'lng' => 109.2], 'radius_km' => 10];

        $this->actingAs($agency)->put("/vi/quan-tri/canh-bao/{$alert->code}/sua", $this->payload([$zone], $geometry))
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(10.0, (float) $alert->fresh()->geometry['radius_km'], 1e-6);
        $this->assertEqualsWithDelta(12.1, (float) $alert->fresh()->geometry['center']['lat'], 1e-6);
    }

    public function test_polygon_geometry_coordinates_are_stored(): void
    {
        $zone = $this->zoneIn('KH');
        $ring = [[109.0, 12.0], [109.4, 12.0], [109.4, 12.4], [109.0, 12.4], [109.0, 12.0]];

        $this->actingAs($this->agency('KH'))->post('/vi/quan-tri/canh-bao/tao',
            $this->payload([$zone], json_encode(['type' => 'Polygon', 'coordinates' => [$ring]])))->assertSessionHasNoErrors();

        $this->assertEquals([$ring], Alert::sole()->geometry['coordinates']);
    }

    public function test_invalid_geometry_children_are_rejected(): void
    {
        $agency = $this->agency('KH');
        $zone = $this->zoneIn('KH');
        $cases = [
            ['type' => 'Circle', 'center' => ['lat' => 95, 'lng' => 109], 'radius_km' => 10],
            ['type' => 'Circle', 'center' => ['lat' => 12, 'lng' => 109], 'radius_km' => 0.05],
            ['type' => 'Circle', 'center' => ['lat' => 12, 'lng' => 109], 'radius_km' => 501],
            ['type' => 'Circle', 'radius_km' => 10],
            ['type' => 'Polygon', 'coordinates' => [[[109, 12], [109.1, 12]]]],
            ['type' => 'Polygon', 'coordinates' => [[[109, 'x'], [109.1, 12], [109.1, 12.1]]]],
        ];
        foreach ($cases as $geometry) {
            $response = $this->actingAs($agency)->post('/vi/quan-tri/canh-bao/tao', $this->payload([$zone], json_encode($geometry)));
            $this->assertTrue(session('errors')?->hasAny(['geometry', 'geometry.*']) ?? false, json_encode($geometry));
            $response->assertRedirect();
        }
        $this->assertSame(0, Alert::count());
    }
}
