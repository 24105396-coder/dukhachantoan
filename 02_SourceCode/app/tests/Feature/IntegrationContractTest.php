<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Booking;
use App\Models\HeritageSite;
use App\Models\Interpretation;
use App\Models\Period;
use App\Models\Tag;
use App\Models\TourRoute;
use Database\Factories\FactoryHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Các điểm tích hợp với frontend (yêu cầu frontend_1.md): trang lỗi Inertia, hồ sơ một phần, form quản trị. */
class IntegrationContractTest extends TestCase
{
    use RefreshDatabase;

    /** Header của một lượt điều hướng Inertia phía client. */
    private function inertiaHeaders(): array
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

        return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version, 'X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_inertia_request_forbidden_renders_error_component_with_status(): void
    {
        $this->actingAs($this->member())->withHeaders($this->inertiaHeaders())->get('/vi/quan-tri')
            ->assertStatus(403)
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 403)
            ->assertJsonPath('props.locale', 'vi')
            ->assertJsonPath('props.auth.user.role', 'member');
    }

    public function test_inertia_request_missing_model_renders_error_404(): void
    {
        $this->withHeaders($this->inertiaHeaders())->get('/en/di-san/khong-ton-tai')
            ->assertNotFound()
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 404)
            ->assertJsonPath('props.locale', 'en');
    }

    public function test_inertia_request_unknown_url_renders_error_with_shared_props(): void
    {
        $response = $this->withHeaders($this->inertiaHeaders())->get('/vi/duong-dan-khong-co')
            ->assertNotFound()
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 404)
            ->assertJsonPath('props.locale', 'vi')
            ->assertJsonPath('props.auth.user', null);

        $this->assertNotEmpty($response->json('props.translations'));
        $this->assertNotEmpty($response->json('props.app.notice'));
    }

    public function test_plain_request_errors_still_use_blade_views(): void
    {
        $this->actingAs($this->member())->get('/vi/quan-tri')->assertForbidden()
            ->assertHeaderMissing('X-Inertia')
            ->assertSee('403');
        $this->get('/vi/duong-dan-khong-co')->assertNotFound()->assertHeaderMissing('X-Inertia');
    }

    public function test_shared_props_include_notice_and_user_phone(): void
    {
        $user = $this->member();
        $user->forceFill(['phone' => '0912345678', 'organization' => 'Ban quản lý'])->save();

        $this->actingAs($user)->get('/vi')->assertInertia(fn (Assert $p) => $p
            ->where('app.notice', 'Sản phẩm học thuật – phục vụ mục đích đào tạo')
            ->where('auth.user.phone', '0912345678')
            ->where('auth.user.organization', 'Ban quản lý'));
        $this->actingAs($user)->get('/en')->assertInertia(fn (Assert $p) => $p
            ->where('app.notice', 'Academic product – for educational purposes only'));
    }

    public function test_profile_patch_with_only_theme_updates_theme_and_returns_back(): void
    {
        $user = $this->member();
        $user->forceFill(['name' => 'Nguyễn Văn A', 'phone' => '0912345678', 'theme' => 'light'])->save();

        $this->actingAs($user)->from('/vi/di-san')->patch('/vi/tai-khoan/ho-so', ['theme' => 'dark'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/vi/di-san')
            ->assertSessionMissing('success');

        $user->refresh();
        $this->assertSame('dark', $user->theme);
        $this->assertSame('Nguyễn Văn A', $user->name);
        $this->assertSame('0912345678', $user->phone);
    }

    public function test_profile_patch_from_theme_toggle_payload_with_null_phone(): void
    {
        $user = $this->member();

        // useTheme().setTheme() gửi lại name/locale hiện tại + phone null
        $this->actingAs($user)->from('/vi')->patch('/vi/tai-khoan/ho-so', [
            'name' => $user->name, 'phone' => null, 'locale' => 'vi', 'theme' => 'dark',
        ])->assertSessionHasNoErrors()->assertRedirect('/vi');

        $this->assertSame('dark', $user->refresh()->theme);
    }

    public function test_profile_patch_partial_still_validates_present_fields(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patchJson('/vi/tai-khoan/ho-so', ['name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingAs($user)->patchJson('/vi/tai-khoan/ho-so', ['theme' => 'blue'])
            ->assertUnprocessable()->assertJsonValidationErrors('theme');
        $this->actingAs($user)->from('/vi/tai-khoan/ho-so')->patch('/vi/tai-khoan/ho-so', ['locale' => 'en'])
            ->assertRedirect('/en/tai-khoan/ho-so');
        $this->assertSame('en', $user->refresh()->locale);
    }

    public function test_admin_urls_accept_numeric_ids(): void
    {
        $admin = $this->admin();
        $site = HeritageSite::factory()->create();
        $tag = Tag::create(['slug' => 'the-thu', 'tag_group' => 'chu_de']);
        $route = TourRoute::factory()->create(['created_by' => $admin->id]);
        Period::create(['code' => 'ly', 'start_year' => 1009, 'end_year' => 1225, 'sort_order' => 1]);

        $this->actingAs($admin)->get("/vi/quan-tri/di-san/{$site->id}/sua")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Sites/Form')->where('site.id', $site->id)
                ->has('site.title_vi')->has('periods.0.id')->has('provinces.0.id'));
        $this->actingAs($admin)->get("/vi/quan-tri/the/{$tag->id}/sua")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Tags/Form')->where('tag.id', $tag->id)->has('tags'));
        $this->actingAs($admin)->get("/vi/quan-tri/tuyen-tham-quan/{$route->id}/sua")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Tours/Form')->where('route.id', $route->id)->has('provinces'));
        // URL công khai vẫn theo slug: id số không mở được trang chi tiết
        $this->get("/vi/di-san/{$site->id}")->assertNotFound();
    }

    public function test_admin_booking_status_accepts_id_or_code(): void
    {
        $admin = $this->admin();
        $first = Booking::factory()->create();
        $second = Booking::factory()->create();

        $this->actingAs($admin)->patch("/vi/quan-tri/dat-lich/{$first->id}/trang-thai", ['status' => 'cancelled', 'reason' => 'Test'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch("/vi/quan-tri/dat-lich/{$second->code}/trang-thai", ['status' => 'cancelled'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Booking::CANCELLED, $first->refresh()->status);
        $this->assertSame(Booking::CANCELLED, $second->refresh()->status);
    }

    /** Sáu ô diễn giải theo thứ tự [vi1, vi2, vi3, en1, en2, en3]. */
    private function sixInterpretations(array $filled): array
    {
        $out = [];
        foreach (['vi', 'en'] as $locale) {
            foreach ([1, 2, 3] as $depth) {
                $out[] = $filled["$locale-$depth"] ?? ['locale' => $locale, 'depth_level' => $depth, 'title' => '', 'summary' => '', 'body' => '', 'sources' => []];
            }
        }

        return $out;
    }

    private function siteForm(array $interpretations, string $status = 'published'): array
    {
        return [
            'slug' => 'chua-thu-nghiem', 'site_type' => 'di_tich', 'recognition_level' => 'quoc_gia',
            'province_id' => FactoryHelpers::defaultProvinceId(), 'period_id' => '', 'address' => '',
            'lat' => 21.0, 'lng' => 105.8, 'year_built' => '', 'year_recognized' => '', 'visiting_hours' => '',
            'ticket_price_vnd' => 0, 'website' => '', 'status' => $status,
            'interpretations' => $interpretations, 'tag_ids' => [], 'figures' => [], 'events' => [],
        ];
    }

    public function test_admin_form_skips_blank_interpretation_slots(): void
    {
        $payload = $this->siteForm($this->sixInterpretations([
            'vi-1' => ['locale' => 'vi', 'depth_level' => 1, 'title' => 'Chùa Thử', 'summary' => 'Tóm tắt', 'body' => 'Nội dung', 'sources' => ['Nguồn 1']],
            'en-1' => ['locale' => 'en', 'depth_level' => 1, 'title' => 'Test Pagoda', 'summary' => '', 'body' => 'Body', 'sources' => []],
        ]));

        $this->actingAs($this->manager())->post('/vi/quan-tri/di-san', $payload)->assertSessionHasNoErrors()->assertRedirect();

        $site = HeritageSite::where('slug', 'chua-thu-nghiem')->firstOrFail();
        $this->assertSame(2, Interpretation::where('subject_type', 'site')->where('subject_id', $site->id)->count());

        $this->actingAs($this->admin())->get("/vi/quan-tri/di-san/{$site->id}/sua")
            ->assertInertia(fn (Assert $p) => $p->where('site.title_vi', 'Chùa Thử')
                ->where('site.interpretations.0.sources', ['Nguồn 1']));
    }

    public function test_admin_form_error_keys_keep_slot_index(): void
    {
        // Ô en-2 (chỉ số 4) có tóm tắt nhưng thiếu tiêu đề → lỗi đúng ô 4; thiếu vi-1 khi công bố → lỗi ô 0
        $payload = $this->siteForm($this->sixInterpretations([
            'en-2' => ['locale' => 'en', 'depth_level' => 2, 'title' => '', 'summary' => 'Only summary', 'body' => '', 'sources' => []],
        ]));

        $this->actingAs($this->manager())->postJson('/vi/quan-tri/di-san', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['interpretations.4.title', 'interpretations.4.body', 'interpretations.0.title']);
    }

    public function test_admin_artifact_index_has_type_enums_and_site_object(): void
    {
        $this->actingAs($this->admin())->get('/vi/quan-tri/hien-vat')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Artifacts/Index')->has('enums.artifact_types')->has('sites'));
        $this->actingAs($this->admin())->get('/vi/quan-tri/nhan-vat')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('enums.figure_types'));
        $this->actingAs($this->admin())->get('/vi/quan-tri/nhan-vat/moi')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('provinces')->has('periods'));
    }

    public function test_sites_index_accepts_frontend_sort_values(): void
    {
        $a = HeritageSite::factory()->create(['slug' => 'a-site', 'year_built' => 1900]);
        $b = HeritageSite::factory()->create(['slug' => 'b-site', 'year_built' => 1000]);
        Interpretation::where('subject_type', 'site')->where('subject_id', $a->id)->where('locale', 'vi')->update(['title' => 'Bảo tàng Z']);
        Interpretation::where('subject_type', 'site')->where('subject_id', $b->id)->where('locale', 'vi')->update(['title' => 'Am A']);

        $this->get('/vi/di-san?sort=name')->assertInertia(fn (Assert $p) => $p->where('filters.sort', 'name')
            ->where('sites.data.0.slug', 'b-site'));
        $this->get('/vi/di-san?sort=oldest')->assertInertia(fn (Assert $p) => $p->where('sites.data.0.slug', 'b-site'));
        $this->get('/vi/di-san?sort=newest')->assertInertia(fn (Assert $p) => $p->where('sites.data.0.slug', 'b-site'));
        $this->get('/vi/di-san?sort=popular')->assertOk();
        $this->get('/vi/di-san?sort=DROP')->assertOk()->assertInertia(fn (Assert $p) => $p->where('filters.sort', null));
    }

    public function test_csp_allows_frontend_sources(): void
    {
        $csp = $this->get('/vi')->headers->get('Content-Security-Policy');

        foreach (['https://tile.openstreetmap.org', 'https://modelviewer.dev', "'nonce-", "'wasm-unsafe-eval'",
            "style-src 'self' 'unsafe-inline'", 'img-src \'self\' data: blob:', 'worker-src \'self\' blob:',
            "connect-src 'self' blob:", 'https://www.gstatic.com'] as $needle) {
            $this->assertStringContainsString($needle, $csp);
        }
        // Font và model-viewer tự host (yêu cầu frontend 18/10): không còn CDN bên thứ ba cho script/style/font
        foreach (['unpkg.com', 'fonts.googleapis.com', 'fonts.gstatic.com', 'ajax.googleapis.com', "'unsafe-eval'"] as $needle) {
            $this->assertStringNotContainsString($needle, $csp);
        }
    }

    public function test_vite_prefetch_is_disabled(): void
    {
        // Prefetch toàn bộ chunk động (model-viewer ~1 MB) làm chậm LCP mobile
        if (! is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Chưa build Vite (npm run build).');
        }
        $this->withVite();
        $html = $this->get('/vi')->assertOk()->getContent();
        $this->assertStringContainsString('/build/assets/', $html);
        $this->assertStringNotContainsString('window.addEventListener(\'load\'', $html);
    }
}
