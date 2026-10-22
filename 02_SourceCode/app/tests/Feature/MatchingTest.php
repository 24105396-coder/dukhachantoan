<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertMatch;
use App\Models\Destination;
use App\Services\AnalyticsClient;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Đối sánh cảnh báo – chặng (F3): dự phòng PHP khi Python tắt, theo vùng, theo Circle, trùng thời gian, idempotent. */
class MatchingTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    /** Python "tắt": AnalyticsClient::match trả null → dùng PHP. */
    private function pythonDown(): void
    {
        $mock = Mockery::mock(AnalyticsClient::class)->makePartial();
        $mock->shouldReceive('match')->andReturnNull();
        $mock->shouldReceive('isUp')->andReturnFalse();
        $this->app->instance(AnalyticsClient::class, $mock);
    }

    public function test_zone_match_uses_php_fallback_and_updates_trip_risk(): void
    {
        $this->pythonDown();
        $zone = $this->zoneIn('KH');
        $other = $this->zoneIn('HN');
        $trip = $this->tripInZone($zone);
        $outside = $this->tripInZone($other);
        $alert = $this->alertForZones([$zone], 4);

        $matches = app(MatchingService::class)->matchAlert($alert);

        $this->assertCount(1, $matches);
        $this->assertSame('zone', $matches->first()->match_type);
        $this->assertSame($trip->id, $matches->first()->trip_id);
        $this->assertSame(4, $trip->fresh()->risk_level_cached);
        $this->assertSame(0, $outside->fresh()->risk_level_cached);
    }

    public function test_child_zone_and_destination_zone_match(): void
    {
        $this->pythonDown();
        $province = $this->zoneIn('KH');
        $special = $this->zoneIn('KH', ['parent_id' => $province->id, 'geometry' => null, 'radius_km' => 10]);
        $destination = Destination::create(['slug' => 'vinh-test', 'zone_id' => $special->id, 'dest_type' => 'bai_bien',
            'lat' => $special->centroid_lat, 'lng' => $special->centroid_lng]);
        $legInChild = $this->tripInZone($special);
        $legWithDestination = $this->tripInZone($province, null, ['destination_id' => $destination->id]);

        // Cảnh báo cấp tỉnh khớp chặng ở vùng con; cảnh báo vùng đặc thù khớp chặng có điểm đến thuộc vùng đó
        $this->assertCount(2, app(MatchingService::class)->matchAlert($this->alertForZones([$province])));
        $special_matches = app(MatchingService::class)->matchAlert($this->alertForZones([$special]));
        $this->assertEqualsCanonicalizing([$legInChild->id, $legWithDestination->id], $special_matches->pluck('trip_id')->all());
        $this->assertContains('destination', $special_matches->pluck('match_type')->all());
    }

    public function test_circle_geometry_match_reports_distance(): void
    {
        $this->pythonDown();
        $zone = $this->zoneIn('KH', ['centroid_lat' => 12.25, 'centroid_lng' => 109.19]);
        $near = $this->tripInZone($zone, null, ['lat' => 12.30, 'lng' => 109.19]);
        $far = $this->tripInZone($zone, null, ['lat' => 13.50, 'lng' => 109.19]);
        $alert = Alert::factory()->severity(3)->create([
            'geometry' => ['type' => 'Circle', 'center' => ['lat' => 12.25, 'lng' => 109.19], 'radius_km' => 15],
        ]);

        $matches = app(MatchingService::class)->matchAlert($alert);

        $this->assertSame([$near->id], $matches->pluck('trip_id')->all());
        $this->assertSame('geometry', $matches->first()->match_type);
        $this->assertEqualsWithDelta(5.6, $matches->first()->distance_km, 0.2);
        $this->assertSame(0, $far->fresh()->risk_level_cached);
    }

    public function test_time_window_must_overlap_and_matching_is_idempotent(): void
    {
        $this->pythonDown();
        $zone = $this->zoneIn('KH');
        $this->tripInZone($zone, null, ['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(6)]);
        $current = $this->tripInZone($zone);
        $alert = $this->alertForZones([$zone], 3, Alert::STATUS_ACTIVE, ['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(12)]);

        app(MatchingService::class)->matchAlert($alert);
        AlertMatch::query()->update(['status' => AlertMatch::STATUS_NOTIFIED]);
        app(MatchingService::class)->matchAlert($alert);

        $this->assertSame(1, AlertMatch::count());
        $this->assertSame($current->id, AlertMatch::first()->trip_id);
        $this->assertSame(AlertMatch::STATUS_NOTIFIED, AlertMatch::first()->status);
    }

    public function test_python_result_is_used_when_service_is_up(): void
    {
        Http::fake(['*/match' => Http::response(['alert_id' => 1, 'matches' => [], 'total' => 0])]);
        $zone = $this->zoneIn('KH');
        $this->tripInZone($zone);
        $alert = $this->alertForZones([$zone]);

        app(MatchingService::class)->matchAlert($alert);

        // Python trả rỗng (và Python mới là bên ghi CSDL) → PHP không chạy dự phòng
        $this->assertSame(0, AlertMatch::count());
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/match') && $request['alert_id'] === $alert->id
            && $request->hasHeader('X-Analytics-Token', 'test-token'));
    }

    public function test_python_error_falls_back_to_php(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'boom'], 500)]);
        $zone = $this->zoneIn('KH');
        $this->tripInZone($zone);

        app(MatchingService::class)->matchAlert($this->alertForZones([$zone]));

        $this->assertSame(1, AlertMatch::count());
    }
}
