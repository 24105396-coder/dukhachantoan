<?php

namespace Tests\Feature;

use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\Interpretation;
use App\Models\MediaAsset;
use App\Models\Tag;
use App\Models\TimelineEvent;
use App\Models\TourSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Ngân sách truy vấn (qa_4): không N+1 (số truy vấn không tăng theo số bản ghi) và
 * khi bộ đệm trang đã ấm thì mỗi trang ≤ 15 truy vấn.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_QUERIES = 15;

    private function makeSites(int $count): HeritageSite
    {
        $tags = collect(['kien-truc', 'le-hoi', 'phat-giao'])->map(fn ($slug) => Tag::firstOrCreate(['slug' => $slug], ['tag_group' => 'chu_de']));
        $sites = HeritageSite::factory()->count($count)->create();
        foreach ($sites as $i => $site) {
            $site->tags()->sync($tags->pluck('id')->all());
            MediaAsset::create([
                'subject_type' => 'site', 'subject_id' => $site->id, 'media_type' => 'image', 'path' => "media/test/{$i}.svg",
                'mime' => 'image/svg+xml', 'sort_order' => 1, 'is_cover' => true, 'license' => 'simulated',
            ]);
            TimelineEvent::create(['site_id' => $site->id, 'event_type' => 'cong_nhan', 'event_year' => 2000 + $i, 'importance' => 2]);
            Artifact::factory()->create(['site_id' => $site->id]);
            $site->figures()->attach(HistoricalFigure::factory()->create()->id, ['relation_type' => 'gan_lien']);
        }

        return $sites->first();
    }

    private function countQueries(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_site_pages_have_no_n_plus_one(): void
    {
        // ≥ 6 bản ghi để mọi khối giới hạn (limit 6/4) của trang chủ đều có dữ liệu ở cả hai lần đo
        $first = $this->makeSites(7);
        config(['disanso.page_cache.ttl' => 0]); // đo truy vấn thật, không qua bộ đệm
        $smallIndex = $this->countQueries('/vi/di-san');
        $this->get("/vi/di-san/{$first->slug}"); // lượt xem đầu ghi page_views (2 truy vấn), chỉ một lần mỗi phiên
        $smallShow = $this->countQueries("/vi/di-san/{$first->slug}");
        $this->get('/vi'); // stats:overview có bộ đệm riêng 600 s
        $smallHome = $this->countQueries('/vi');

        $this->makeSites(10);
        $this->assertSame($smallIndex, $this->countQueries('/vi/di-san'), 'Danh sách di sản có N+1');
        $this->assertSame($smallShow, $this->countQueries("/vi/di-san/{$first->slug}"), 'Chi tiết di sản có N+1');
        $this->assertSame($smallHome, $this->countQueries('/vi'), 'Trang chủ có N+1');
    }

    public function test_warm_cache_keeps_pages_within_query_budget(): void
    {
        $first = $this->makeSites(8);
        TourSlot::factory()->create();

        foreach (['/vi', '/vi/di-san', '/vi/di-san?page=1', "/vi/di-san/{$first->slug}", '/en/di-san'] as $url) {
            $this->get($url)->assertOk(); // làm ấm
            $this->assertLessThanOrEqual(self::MAX_QUERIES, $this->countQueries($url), $url);
        }
    }

    public function test_content_change_invalidates_page_cache(): void
    {
        $site = HeritageSite::factory()->create();
        $this->get('/vi/di-san')->assertInertia(fn (Assert $p) => $p->where('sites.total', 1));

        HeritageSite::factory()->create();
        $this->get('/vi/di-san')->assertInertia(fn (Assert $p) => $p->where('sites.total', 2)->has('mapPoints', 2));

        Interpretation::where('subject_type', 'site')->where('subject_id', $site->id)->where('locale', 'vi')
            ->first()->update(['title' => 'Tiêu đề đã sửa']);
        $this->get("/vi/di-san/{$site->slug}")->assertInertia(fn (Assert $p) => $p->where('site.title', 'Tiêu đề đã sửa'));
        $this->get('/vi')->assertInertia(fn (Assert $p) => $p->where('featuredSites.0.title', fn ($t) => is_string($t)));
    }

    public function test_cached_listing_does_not_leak_foreign_query_into_links(): void
    {
        HeritageSite::factory()->count(13)->create();

        $this->get('/vi/di-san?utm=%3Cx%3E')->assertOk();
        $this->get('/vi/di-san')->assertInertia(fn (Assert $p) => $p->where('sites.links', fn ($links) => ! str_contains(json_encode($links), 'utm')));
    }
}
