<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\RiskScore;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 500)]);
    }

    public function test_home_renders_contract_props_with_empty_data(): void
    {
        $this->get('/vi')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Home')
            ->where('national.level', 0)
            ->where('national.label', 'Bình thường')
            ->where('zonesGeo', ['type' => 'FeatureCollection', 'features' => []])
            ->where('topAlerts', [])
            ->where('weatherHighlights', [])
            ->where('guides', [])
            ->where('stats', ['zones' => 0, 'alerts_active' => 0, 'trips_active' => 0, 'contacts' => 0])
            ->where('auth.user', null)
            ->where('app.name', 'DuKhachAnToan')
            ->where('app.analyticsUp', false)
            ->where('risk', ['national_level' => 0, 'active_alerts' => 0, 'updated_at' => null]));
    }

    public function test_national_risk_uses_highest_live_alert_or_score(): void
    {
        Alert::factory()->severity(3)->create();
        Alert::factory()->severity(4)->draft()->create();
        RiskScore::create(['zone_id' => Zone::factory()->create()->id, 'computed_at' => now(), 'score' => 40, 'level' => 2]);
        Cache::flush();

        $this->get('/en')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('national.level', 3)
            ->where('national.label', 'Warning')
            ->where('risk.active_alerts', 1)
            ->where('stats.alerts_active', 1));
    }

    public function test_about_lists_active_sources(): void
    {
        $this->seed(\Database\Seeders\AlertSourceSeeder::class);

        $this->get('/vi/gioi-thieu')->assertOk()->assertInertia(fn (Assert $page) => $page->component('About')
            ->has('sources', 3)
            ->where('sources.0.code', 'openmeteo')
            ->where('sources.0.license', 'CC BY 4.0')
            ->has('team'));
    }

    public function test_api_stats_overview_is_public_json(): void
    {
        $this->getJson('/api/v1/stats/overview?locale=en')->assertOk()
            ->assertJsonStructure(['data' => ['zones', 'alerts_active', 'trips_active', 'contacts', 'national_level', 'national_label'], 'updated_at'])
            ->assertJsonPath('data.national_label', 'Normal');
    }

    public function test_api_unknown_route_returns_json_404(): void
    {
        $this->getJson('/api/v1/sites')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_openapi_document_is_served(): void
    {
        $this->get('/api/v1/openapi.json')->assertOk()->assertHeader('Content-Type', 'application/json');
    }

    public function test_heritage_routes_are_removed(): void
    {
        foreach (['/vi/di-san', '/vi/hien-vat', '/vi/tuyen-tham-quan', '/vi/tim-kiem'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
