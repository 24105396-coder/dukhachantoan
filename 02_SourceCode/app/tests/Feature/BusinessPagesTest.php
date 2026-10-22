<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\SafetyCheckin;
use App\Models\WeatherObservation;
use App\Services\MatchingService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Props thật của trang chủ, bản đồ, thời tiết, dashboard, hành trình theo page-contract. */
class BusinessPagesTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    public function test_home_has_zones_geo_top_alerts_and_weather(): void
    {
        $zone = $this->zoneIn('KH', ['code' => 'vinh-nha-trang']);
        $this->alertForZones([$zone], 4);
        WeatherObservation::create(['zone_id' => $zone->id, 'observed_at' => now(), 'temp_c' => 30, 'weather_code' => 1, 'fetched_at' => now()]);

        $this->get('/vi')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Home')
            ->where('zonesGeo.features.0.properties.code', 'vinh-nha-trang')
            ->where('zonesGeo.features.0.properties.level', 4)
            ->has('topAlerts', 1)->where('topAlerts.0.severity', 4)
            ->has('weatherHighlights', 1)->where('weatherHighlights.0.zone.code', 'vinh-nha-trang')
            ->where('weatherHighlights.0.weather_label', 'Ít mây')
            ->where('national.level', 4));
    }

    public function test_map_weather_contacts_guides_pages_render(): void
    {
        $zone = $this->zoneIn('KH', ['code' => 'ha-noi']);
        Alert::factory()->create(['geometry' => ['type' => 'Circle', 'center' => ['lat' => 12, 'lng' => 109], 'radius_km' => 10]]);
        $traveler = $this->traveler();
        $this->tripInZone($zone, $traveler);

        $this->actingAs($traveler)->get('/vi/ban-do')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Map/Index')
            ->has('alerts', 1)->has('legend', 5)->has('myLegs', 1)->where('center.zoom', 5));
        $this->get('/vi/thoi-tiet')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Weather/Show')
            ->where('zone.code', 'ha-noi')->where('now', null)->has('forecast', 0)->has('zones', 1));
        $this->get('/vi/thoi-tiet/khong-co')->assertNotFound();
        $this->get('/vi/danh-ba-khan-cap')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Contacts/Index')
            ->has('contacts')->has('national')->has('types', 9));
        $this->get('/vi/huong-dan-ung-pho')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Guides/Index')->has('guides'));
    }

    public function test_dashboard_lists_affected_trips_need_help_and_queue(): void
    {
        $zone = $this->zoneIn('KH');
        $trip = $this->tripInZone($zone);
        $alert = $this->alertForZones([$zone], 4);
        app(NotificationService::class)->enqueueMatches(app(MatchingService::class)->matchAlert($alert));
        SafetyCheckin::factory()->needHelp()->create(['trip_id' => $trip->id]);
        $otherTrip = $this->tripInZone($this->zoneIn('HN'));
        SafetyCheckin::factory()->needHelp()->create(['trip_id' => $otherTrip->id]);

        $this->actingAs($this->agency('KH'))->get('/vi/quan-tri')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('affectedTrips', 1)->where('affectedTrips.0.code', $trip->code)->where('affectedTrips.0.risk_level', 4)
            ->where('affectedTrips.0.active_alerts_count', 1)
            ->has('needHelp', 1)->has('recentAlerts', 1)
            ->where('kpis.trips_in_affected_zones', 1)
            ->where('queueHealth.queued', fn ($v) => $v > 0)
            ->where('zonesGeo.features.0.properties.level', 4));
    }

    public function test_trip_detail_shows_matches_and_staff_sees_full_phone(): void
    {
        $zone = $this->zoneIn('KH');
        $trip = $this->tripInZone($zone);
        $trip->members()->create(['full_name' => 'Trưởng đoàn', 'phone' => '0912345678', 'is_leader' => true]);
        app(MatchingService::class)->matchAlert($this->alertForZones([$zone], 3));

        $this->actingAs($trip->user)->get("/vi/hanh-trinh/{$trip->code}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('trip.matches', 1)->where('trip.matches.0.match_type', 'zone')->where('trip.risk_level', 3)
            ->where('trip.legs.0.risk_level', 3)->has('trip.legs.0.alerts', 1)
            ->where('trip.members.0.phone_masked', '091****678')->missing('trip.members.0.phone'));
        $this->actingAs($this->agency('KH'))->get("/vi/quan-tri/hanh-trinh/{$trip->code}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Trips/Show')->where('trip.members.0.phone', '0912345678'));
    }
}
