<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\EmergencyContact;
use App\Models\ResponseGuide;
use App\Models\WeatherForecast;
use App\Models\WeatherObservation;
use Database\Factories\FactoryHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** API v1 công khai: cảnh báo, GeoJSON, vùng, thời tiết, danh bạ, hướng dẫn. */
class ApiBusinessTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    public function test_alerts_list_filters_and_hides_drafts(): void
    {
        $kh = $this->zoneIn('KH');
        $hn = $this->zoneIn('HN');
        $this->alertForZones([$kh], 4);
        $this->alertForZones([$hn], 2);
        $draft = $this->alertForZones([$kh], 4, Alert::STATUS_DRAFT);
        $this->alertForZones([$kh], 3, Alert::STATUS_EXPIRED);

        $this->getJson('/api/v1/alerts')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.severity', 4)
            ->assertJsonStructure(['data' => [['code', 'severity', 'severity_label', 'hazard', 'headline', 'zones', 'source' => ['name', 'url'], 'updated_at_source', 'is_simulated', 'url']], 'meta', 'links']);
        $this->getJson('/api/v1/alerts?province=HN')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.severity', 2);
        $this->getJson('/api/v1/alerts?severity=4&lang=en&locale=en')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/alerts?active=0')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson("/api/v1/alerts/{$draft->code}")->assertNotFound();
    }

    public function test_alert_detail_has_no_affected_trip_count(): void
    {
        $alert = $this->alertForZones([$this->zoneIn('KH')], 3);

        $this->getJson("/api/v1/alerts/{$alert->code}")->assertOk()->assertJsonPath('data.code', $alert->code)
            ->assertJsonMissingPath('data.affected_trips_count')->assertJsonStructure(['data' => ['guides', 'contacts', 'history', 'translations_available']]);
    }

    public function test_alerts_geojson_returns_live_features(): void
    {
        $zone = $this->zoneIn('KH');
        $this->alertForZones([$zone], 3);
        Alert::factory()->severity(4)->create(['geometry' => ['type' => 'Circle', 'center' => ['lat' => 12.2, 'lng' => 109.2], 'radius_km' => 20]]);
        $this->alertForZones([$zone], 2, Alert::STATUS_CANCELLED);

        $response = $this->getJson('/api/v1/alerts.geojson')->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')->assertJsonCount(2, 'features')
            ->assertJsonPath('features.0.geometry.type', 'Point')->assertJsonPath('features.0.properties.radius_km', 20)
            ->assertJsonPath('features.1.geometry.type', 'Polygon');
        $this->assertStringContainsString('geo+json', $response->headers->get('Content-Type'));
    }

    public function test_zones_geojson_and_weather(): void
    {
        $zone = $this->zoneIn('KH');
        $this->alertForZones([$zone], 3);
        WeatherObservation::create(['zone_id' => $zone->id, 'observed_at' => now(), 'temp_c' => 31.5, 'weather_code' => 63, 'fetched_at' => now()]);
        WeatherForecast::create(['zone_id' => $zone->id, 'forecast_date' => now()->toDateString(), 'tmax_c' => 33, 'weather_code' => 95, 'fetched_at' => now()]);

        $this->getJson('/api/v1/zones.geojson')->assertOk()->assertJsonPath('features.0.properties.code', $zone->code)
            ->assertJsonPath('features.0.properties.level', 3)->assertJsonPath('features.0.properties.active_alerts_count', 1);
        $this->getJson('/api/v1/zones')->assertOk()->assertJsonPath('data.0.risk.level', 3);
        $this->getJson("/api/v1/zones/{$zone->code}/weather")->assertOk()
            ->assertJsonPath('data.now.temp_c', 31.5)->assertJsonPath('data.now.icon', 'cloud-rain')->assertJsonPath('data.now.source', 'Open-Meteo')
            ->assertJsonPath('data.forecast.0.icon', 'cloud-lightning')->assertJsonPath('data.risk.level', 3);
        $this->getJson('/api/v1/zones/khong-co/weather')->assertNotFound();
    }

    public function test_contacts_and_guides_endpoints(): void
    {
        $kh = FactoryHelpers::provinceId('KH');
        EmergencyContact::create(['province_id' => $kh, 'contact_type' => 'police', 'phone' => '113', 'source_name' => 'Bộ TT&TT'])
            ->syncTranslations(['vi' => ['name' => 'Công an Khánh Hòa']]);
        EmergencyContact::create(['province_id' => FactoryHelpers::provinceId('HN'), 'contact_type' => 'police', 'phone' => '113', 'source_name' => 'x']);
        EmergencyContact::create(['contact_type' => 'hotline_national', 'phone' => '112', 'source_name' => 'x']);
        $guide = ResponseGuide::create(['slug' => 'ung-pho-bao', 'hazard_type_id' => FactoryHelpers::hazardTypeId('bao'), 'status' => 'published']);
        $guide->syncTranslations(['vi' => ['title' => 'Ứng phó bão', 'before_steps' => ['Theo dõi bản tin'], 'sources' => ['NCHMF']]]);
        ResponseGuide::create(['slug' => 'nhap', 'hazard_type_id' => FactoryHelpers::hazardTypeId('bao'), 'status' => 'draft']);

        $this->getJson('/api/v1/contacts?province=KH')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.phone', '112');
        $this->getJson('/api/v1/guides?hazard=bao')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/guides/ung-pho-bao')->assertOk()->assertJsonPath('data.before_steps.0', 'Theo dõi bản tin');
        $this->getJson('/api/v1/guides/nhap')->assertNotFound();
    }

    public function test_openapi_documents_business_endpoints(): void
    {
        $response = $this->get('/api/v1/openapi.json')->assertOk();
        $spec = json_decode($response->baseResponse->getFile()->getContent(), true);

        $this->assertSame('3.1.0', $spec['openapi']);
        foreach (['/alerts', '/alerts/{code}', '/alerts.geojson', '/zones', '/zones.geojson', '/zones/{code}/weather', '/contacts',
            '/guides', '/guides/{slug}', '/trips/shared/{token}', '/trips/shared/{token}/checkin', '/stats/overview'] as $path) {
            $this->assertArrayHasKey($path, $spec['paths'], $path);
        }
    }
    /** page-contract dùng ?lang=, OpenAPI ghi ?locale=: cả hai đều đổi ngôn ngữ nội dung. */
    public function test_api_accepts_lang_and_locale_query(): void
    {
        $this->alertForZones([$this->zoneIn('KH')], 3);

        $this->getJson('/api/v1/alerts?lang=en')->assertOk()->assertJsonPath('meta.locale', 'en');
        $this->getJson('/api/v1/alerts?locale=en')->assertOk()->assertJsonPath('meta.locale', 'en');
        $this->getJson('/api/v1/alerts?lang=fr')->assertOk()->assertJsonPath('meta.locale', 'vi');
    }
}
