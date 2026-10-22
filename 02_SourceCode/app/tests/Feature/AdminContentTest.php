<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\MediaAsset;
use App\Models\Province;
use App\Models\Tag;
use App\Models\TourRoute;
use Database\Factories\FactoryHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    private function sitePayload(array $override = []): array
    {
        $tag = Tag::firstOrCreate(['slug' => 'kinh-do-co'], ['tag_group' => 'chu_de']);
        $figure = HistoricalFigure::query()->first() ?? HistoricalFigure::factory()->create();

        return array_merge([
            'slug' => 'den-moi', 'site_type' => 'di_tich', 'province_id' => FactoryHelpers::defaultProvinceId(),
            'lat' => 21.03, 'lng' => 105.85, 'recognition_level' => 'quoc_gia', 'status' => 'published',
            'interpretations' => [
                ['locale' => 'vi', 'depth_level' => 1, 'title' => 'Đền Mới', 'summary' => 'Tóm tắt', 'body' => 'Nội dung', 'sources' => ['Nguồn A', 'Nguồn B']],
                ['locale' => 'en', 'depth_level' => 1, 'title' => 'New Temple', 'body' => 'Body'],
            ],
            'tag_ids' => [$tag->id],
            'figures' => [['figure_id' => $figure->id, 'relation_type' => 'duoc_tho']],
            'events' => [['event_type' => 'xay_dung', 'event_year' => 1500, 'title_vi' => 'Khởi dựng', 'title_en' => 'Built']],
        ], $override);
    }

    public function test_manager_creates_and_updates_site_with_interpretations_tags_figures_events(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->post('/vi/quan-tri/di-san', $this->sitePayload())->assertSessionHasNoErrors()->assertRedirect();

        $site = HeritageSite::where('slug', 'den-moi')->firstOrFail();
        $this->assertSame(2, $site->interpretations()->count());
        $this->assertSame("Nguồn A\nNguồn B", $site->interpretation('vi', 1)->sources);
        $this->assertSame(1, $site->tags()->count());
        $this->assertSame('duoc_tho', $site->figures()->first()->pivot->relation_type);
        $this->assertSame(1, $site->timelineEvents()->count());
        $this->assertDatabaseHas('taggables', ['taggable_type' => 'site', 'taggable_id' => $site->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.site.create', 'user_id' => $manager->id]);

        $payload = $this->sitePayload(['slug' => 'den-moi', 'interpretations' => [
            ['locale' => 'vi', 'depth_level' => 1, 'title' => 'Đền Mới (sửa)', 'body' => 'Nội dung mới'],
        ]]);
        $this->actingAs($manager)->put("/vi/quan-tri/di-san/{$site->slug}", $payload)->assertSessionHasNoErrors();

        $site->refresh()->load('interpretations');
        $this->assertSame(1, $site->interpretations->count());
        $this->assertSame(2, $site->interpretation('vi', 1)->version);
    }

    public function test_published_site_requires_vietnamese_depth_one(): void
    {
        $this->actingAs($this->admin())->postJson('/vi/quan-tri/di-san', $this->sitePayload(['interpretations' => [
            ['locale' => 'en', 'depth_level' => 1, 'title' => 'Only English', 'body' => 'Body'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors('interpretations');
    }

    public function test_admin_site_index_and_form_props(): void
    {
        $site = HeritageSite::factory()->create();

        $this->actingAs($this->admin())->get('/vi/quan-tri/di-san')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Sites/Index')
                ->where('sites.data.0.slug', $site->slug)->where('sites.data.0.depths_vi', [1])->where('sites.data.0.depths_en', [1]));
        $this->actingAs($this->admin())->get("/vi/quan-tri/di-san/{$site->slug}/sua")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Sites/Form')->where('site.slug', $site->slug)
                ->has('site.interpretations', 2)->has('enums.site_types')->has('enums.licenses')->has('figures')->has('provinces'));
        $this->actingAs($this->admin())->get('/vi/quan-tri/di-san/moi')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Sites/Form')->where('site', null));
    }

    public function test_admin_soft_deletes_site(): void
    {
        $site = HeritageSite::factory()->create();

        $this->actingAs($this->admin())->delete("/vi/quan-tri/di-san/{$site->slug}")->assertRedirect();
        $this->assertSoftDeleted($site);
    }

    public function test_admin_dashboard_kpis(): void
    {
        HeritageSite::factory()->count(2)->create();

        $this->actingAs($this->admin())->get('/vi/quan-tri')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Dashboard')
            ->where('kpis.sites', 2)->has('charts.viewsByDay', 30)->has('charts.bookingsByStatus')
            ->has('charts.sitesByProvince')->has('charts.searchEngines')->has('recentAudit')->where('analyticsUp', false));
    }

    public function test_contributor_creates_route_and_slot(): void
    {
        $owner = $this->contributor();
        $site = HeritageSite::factory()->create();

        $this->actingAs($owner)->post('/vi/quan-tri/tuyen-tham-quan', [
            'slug' => 'tuyen-moi', 'duration_minutes' => 180, 'difficulty' => 'de', 'price_vnd' => 150000,
            'default_capacity' => 15, 'status' => 'published',
            'translations' => ['vi' => ['name' => 'Tuyến mới'], 'en' => ['name' => 'New route']],
            'stops' => [['site_id' => $site->id, 'dwell_minutes' => 60]],
        ])->assertSessionHasNoErrors();

        $route = TourRoute::where('slug', 'tuyen-moi')->firstOrFail();
        $this->assertSame($owner->id, $route->created_by);
        $this->assertSame(1, $route->stops()->count());

        $this->actingAs($owner)->post("/vi/quan-tri/tuyen-tham-quan/{$route->slug}/khung-gio", [
            'starts_at' => now()->addDays(3)->setTime(9, 0)->toDateTimeString(), 'capacity' => 15, 'language' => 'vi',
        ])->assertSessionHas('success');
        $this->assertSame(1, $route->slots()->count());
        $this->assertEquals(180, $route->slots()->first()->starts_at->diffInMinutes($route->slots()->first()->ends_at));
    }

    public function test_media_upload_stores_file_with_metadata(): void
    {
        Storage::fake('public');
        $site = HeritageSite::factory()->create();

        $this->actingAs($this->admin())->post('/vi/quan-tri/media', [
            'subject_type' => 'site', 'subject_id' => $site->id, 'media_type' => 'image',
            'file' => UploadedFile::fake()->image('anh.png', 800, 600),
            'caption_vi' => 'Cổng chính', 'caption_en' => 'Main gate', 'source_name' => 'Nhóm DiSanSo',
            'license' => 'cc_by', 'author' => 'Nhóm', 'is_cover' => true,
        ])->assertSessionHasNoErrors();

        $media = MediaAsset::firstOrFail();
        Storage::disk('public')->assertExists($media->path);
        $this->assertStringStartsWith('media/image/', $media->path);
        $this->assertSame(800, $media->width);
        $this->assertSame($media->id, $site->fresh()->cover_media_id);
        $this->assertSame(2, $media->captions()->count());
    }

    public function test_quality_report_is_fetched_and_cached(): void
    {
        Http::fake(['*/quality-report' => Http::response([
            'generated_at' => '2026-10-18T10:00:00+07:00', 'summary' => ['errors' => 1, 'warnings' => 2, 'checks' => 2],
            'checks' => [['code' => 'missing_en', 'severity' => 'error', 'title' => 'Thiếu bản tiếng Anh', 'count' => 1, 'samples' => ['den-a']]],
        ])]);

        $this->actingAs($this->admin())->post('/vi/quan-tri/chat-luong-du-lieu/kiem-tra')->assertSessionHas('success');
        $this->actingAs($this->admin())->get('/vi/quan-tri/chat-luong-du-lieu')->assertInertia(fn (Assert $p) => $p
            ->component('Admin/DataQuality')->where('report.summary.errors', 1)->where('report.checks.0.code', 'missing_en'));
    }

    public function test_export_bookings_csv(): void
    {
        $response = $this->actingAs($this->admin())->get('/vi/quan-tri/thong-ke/xuat?type=bookings')->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('code,status,route', $response->streamedContent());
    }

    public function test_review_contribution(): void
    {
        $contribution = Contribution::create([
            'user_id' => $this->member()->id, 'contribution_type' => 'correction', 'locale' => 'vi',
            'title' => 'Sửa năm xây dựng', 'body' => str_repeat('Chi tiết ', 5), 'status' => 'pending',
        ]);

        $this->actingAs($this->manager())->patch("/vi/quan-tri/kiem-duyet/{$contribution->id}", ['status' => 'approved'])
            ->assertSessionHas('success');
        $this->assertSame('approved', $contribution->fresh()->status);
        $this->assertNotNull($contribution->fresh()->reviewed_at);

        $this->actingAs($this->manager())->patchJson("/vi/quan-tri/kiem-duyet/{$contribution->id}", ['status' => 'rejected'])
            ->assertUnprocessable()->assertJsonValidationErrors('review_note');
    }
}
