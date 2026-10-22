<?php

namespace Tests\Feature;

use App\Models\Artifact;
use App\Models\Favorite;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\Interpretation;
use App\Models\PageView;
use App\Models\TourSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_has_contract_props(): void
    {
        HeritageSite::factory()->count(2)->create();
        TourSlot::factory()->create();

        $this->get('/vi')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Home')
            ->has('featuredSites', 2)->has('latestArtifacts')->has('upcomingRoutes', 1)->has('featuredFigures')
            ->has('mapPoints', 2)
            ->has('stats', fn (Assert $s) => $s->hasAll(['sites', 'artifacts', 'figures', 'provinces', 'intangible'])));
    }

    public function test_sites_index_is_paginated_and_filterable(): void
    {
        HeritageSite::factory()->count(13)->create();
        HeritageSite::factory()->create(['site_type' => 'bao_tang']);

        $this->get('/vi/di-san')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Sites/Index')
            ->has('sites.data', 12)->where('sites.total', 14)->where('sites.per_page', 12)->has('sites.links')
            ->has('mapPoints', 14)->has('provinces')->has('periods')->has('tags'));

        $this->get('/vi/di-san?type=bao_tang')->assertInertia(fn (Assert $page) => $page->where('sites.total', 1)
            ->where('filters.type', 'bao_tang'));
    }

    public function test_site_detail_records_one_view_per_session_and_supports_depth(): void
    {
        $site = HeritageSite::factory()->create();
        Interpretation::factory()->forSubject($site)->depth(2, 'vi')->create(['body' => "**Đậm** <script>alert(1)</script>\n\n- mục"]);

        $this->get("/vi/di-san/{$site->slug}?depth=2")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Show')->where('depth', 2)->where('site.interpretation.depth_level', 2)
            ->where('site.available_depths', [1, 2])
            ->where('site.interpretation.body_html', fn ($html) => str_contains($html, '<strong>Đậm</strong>') && ! str_contains($html, '<script'))
            ->where('isFavorite', false)
            ->has('site.media.images')->has('site.media.audios')->has('site.jsonld')->has('related'));
        $this->get("/vi/di-san/{$site->slug}");

        $this->assertSame(1, PageView::count());
        // view_count cộng theo lô mỗi phút (app:sync-view-counts), không UPDATE theo từng lượt xem
        $this->assertSame(0, $site->fresh()->view_count);
        $this->artisan('app:sync-view-counts')->assertSuccessful();
        $this->assertSame(1, $site->fresh()->view_count);
        $this->artisan('app:sync-view-counts')->assertSuccessful(); // chạy lại không đếm trùng
        $this->assertSame(1, $site->fresh()->view_count);
        $this->assertSame('site', PageView::first()->subject_type);
    }

    public function test_draft_site_is_not_public(): void
    {
        $site = HeritageSite::factory()->draft()->create();

        $this->get("/vi/di-san/{$site->slug}")->assertNotFound();
    }

    public function test_artifact_and_figure_pages_render(): void
    {
        $artifact = Artifact::factory()->create();
        $figure = HistoricalFigure::factory()->create();

        $this->get('/vi/hien-vat')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Artifacts/Index')->has('artifacts.data', 1)->has('types'));
        $this->get("/vi/hien-vat/{$artifact->slug}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Artifacts/Show')->where('artifact.slug', $artifact->slug));
        $this->get('/vi/nhan-vat')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Figures/Index')->has('figures.data', 1));
        $this->get("/vi/nhan-vat/{$figure->slug}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Figures/Show')->where('figure.slug', $figure->slug));
    }

    public function test_tour_pages_timeline_map_tags_about(): void
    {
        $slot = TourSlot::factory()->create();

        $this->get('/vi/tuyen-tham-quan')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tours/Index')->has('routes.data', 1));
        $this->get('/vi/tuyen-tham-quan/'.$slot->route->slug)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tours/Show')
            ->where('holdMinutes', 10)->where('canBook', false)->has('route.slots', 1)->where('route.slots.0.available', 20));
        $this->get('/vi/dong-thoi-gian')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Timeline/Index')->has('events')->has('periods'));
        $this->get('/vi/ban-do')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Map/Index')->where('geojson.type', 'FeatureCollection'));
        $this->get('/vi/the')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tags/Index')->has('groups'));
        $this->get('/vi/gioi-thieu')->assertOk()->assertInertia(fn (Assert $p) => $p->component('About')->has('stats'));
    }

    public function test_tour_index_province_count_is_number_of_published_routes(): void
    {
        HeritageSite::factory()->count(5)->create(); // cùng tỉnh mặc định: 5 di sản
        TourSlot::factory()->create();               // 1 tuyến công bố
        \App\Models\TourRoute::factory()->draft()->create();

        $this->get('/vi/tuyen-tham-quan')->assertInertia(fn (Assert $p) => $p->component('Tours/Index')
            ->has('provinces', 1)->where('provinces.0.code', 'HN')->where('provinces.0.count', 1));
    }

    public function test_member_can_toggle_favorite(): void
    {
        $site = HeritageSite::factory()->create();
        $user = $this->member();

        $this->actingAs($user)->post('/vi/yeu-thich', ['type' => 'site', 'id' => $site->id])->assertSessionHas('success');
        $this->assertSame(1, Favorite::count());
        $this->actingAs($user)->get("/vi/di-san/{$site->slug}")->assertInertia(fn (Assert $p) => $p->where('isFavorite', true));
        $this->actingAs($user)->post('/vi/yeu-thich', ['type' => 'site', 'id' => $site->id]);
        $this->assertSame(0, Favorite::count());
    }
}
