<?php

namespace Tests\Feature;

use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\TimelineEvent;
use App\Models\TourSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sites_index_returns_cards_with_meta_and_rate_limit_headers(): void
    {
        HeritageSite::factory()->count(3)->create();
        HeritageSite::factory()->draft()->create();

        $this->getJson('/api/v1/sites?per_page=2')->assertOk()
            ->assertHeader('X-RateLimit-Limit', 60)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'slug', 'title', 'summary', 'site_type', 'recognition_level', 'province' => ['code', 'name'], 'lat', 'lng', 'cover', 'tags', 'url']], 'meta', 'links']);
    }

    public function test_site_detail_is_localized_and_has_jsonld(): void
    {
        $site = HeritageSite::factory()->create();

        $vi = $this->getJson("/api/v1/sites/{$site->slug}?locale=vi")->assertOk();
        $en = $this->getJson("/api/v1/sites/{$site->slug}?locale=en")->assertOk();

        $this->assertStringStartsWith('Di tích', $vi->json('data.title'));
        $this->assertStringStartsWith('Site', $en->json('data.title'));
        $this->assertStringContainsString('/en/di-san/', $en->json('data.url'));
        $this->assertSame('TouristAttraction', $vi->json('data.jsonld.@type'));
        $this->assertNotNull($vi->json('data.interpretation.body_html'));
    }

    public function test_unknown_or_draft_slug_returns_404(): void
    {
        $draft = HeritageSite::factory()->draft()->create();

        $this->getJson('/api/v1/sites/khong-ton-tai')->assertNotFound();
        $this->getJson("/api/v1/sites/{$draft->slug}")->assertNotFound();
    }

    public function test_api_errors_are_json_without_accept_header_and_hide_model_class(): void
    {
        // Không gửi Accept: application/json (giống curl/trình duyệt)
        $response = $this->get('/api/v1/sites/khong-ton-tai');
        $response->assertNotFound()->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Not found.']);
        $this->assertStringNotContainsString('App\\Models', $response->getContent());
        $this->assertStringNotContainsString('HeritageSite', $response->getContent());

        $this->getJson('/api/v1/artifacts/khong-ton-tai')->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->get('/api/v1/khong-co-route')->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->delete('/api/v1/sites')->assertStatus(405)->assertExactJson(['message' => 'Method not allowed.']);
    }

    public function test_api_server_error_and_forbidden_are_generic_json(): void
    {
        Route::middleware('api')->get('/api/v1/_boom', fn () => throw new \RuntimeException('SQLSTATE secret /var/www/html'));
        Route::middleware('api')->get('/api/v1/_deny', fn () => abort(403, 'Chi tiết nội bộ'));

        $boom = $this->get('/api/v1/_boom');
        $boom->assertStatus(500)->assertExactJson(['message' => 'Server error.']);
        $this->assertStringNotContainsString('SQLSTATE', $boom->getContent());

        $this->get('/api/v1/_deny')->assertForbidden()->assertExactJson(['message' => 'Forbidden.']);
    }

    public function test_web_404_still_renders_html(): void
    {
        $this->get('/vi/di-san/khong-ton-tai')->assertNotFound()->assertHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function test_artifacts_and_figures_endpoints(): void
    {
        $artifact = Artifact::factory()->create();
        $figure = HistoricalFigure::factory()->create();

        $this->getJson('/api/v1/artifacts')->assertOk()->assertJsonPath('data.0.slug', $artifact->slug);
        $this->getJson("/api/v1/artifacts/{$artifact->slug}")->assertOk()->assertJsonPath('data.jsonld.@type', 'VisualArtwork');
        $this->getJson('/api/v1/figures')->assertOk()->assertJsonPath('data.0.slug', $figure->slug);
        $this->getJson("/api/v1/figures/{$figure->slug}")->assertOk()->assertJsonPath('data.jsonld.@type', 'Person');
    }

    public function test_geojson_is_feature_collection_with_lng_lat_order(): void
    {
        HeritageSite::factory()->create(['lat' => 21.03, 'lng' => 105.84]);

        $this->getJson('/api/v1/geojson/sites')->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('features.0.geometry.coordinates', [105.84, 21.03]);
    }

    public function test_timeline_filters_by_year_range(): void
    {
        $site = HeritageSite::factory()->create();
        foreach ([1010, 1802, 1945] as $year) {
            $e = TimelineEvent::create(['site_id' => $site->id, 'event_type' => 'su_kien', 'event_year' => $year, 'importance' => 2]);
            $e->translations()->create(['locale' => 'vi', 'title' => "Sự kiện $year"]);
        }

        $this->getJson('/api/v1/timeline?from=1000&to=1900')->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.year', 1010)->assertJsonPath('0.subject.type', 'site');
    }

    public function test_tags_endpoint(): void
    {
        $this->getJson('/api/v1/tags')->assertOk()->assertJson([]);
    }

    public function test_openapi_specification_is_served(): void
    {
        $this->get('/api/v1/openapi.json')->assertOk()->assertHeader('Content-Type', 'application/json');
        $spec = json_decode(file_get_contents(resource_path('openapi/openapi.json')), true);
        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('/api/v1/sites', $spec['paths']);
    }

    public function test_ical_feed_lists_upcoming_slots(): void
    {
        $slot = TourSlot::factory()->create();
        $route = $slot->route;

        $response = $this->get("/api/v1/ical/routes/{$route->slug}.ics")->assertOk();

        $this->assertStringStartsWith('text/calendar', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('UID:slot-'.$slot->id.'@disanso', $response->getContent());
    }

    public function test_api_allows_cross_origin_requests(): void
    {
        $this->getJson('/api/v1/tags', ['Origin' => 'https://example.org'])
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
