<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\HeritageSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_vietnamese(): void
    {
        $this->get('/')->assertRedirect('/vi');
    }

    public function test_unsupported_locale_returns_404(): void
    {
        $this->get('/fr/di-san')->assertNotFound();
    }

    public function test_english_prefix_sets_locale_and_shared_props(): void
    {
        $this->get('/en/di-san?type=di_tich')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Sites/Index')
                ->where('locale', 'en')
                ->where('locales.0.code', 'vi')
                ->where('locales.1.label', 'English')
                ->where('currentUrlOtherLocale', url('/vi/di-san').'?type=di_tich')
                ->where('app.name', 'DiSanSo')
                ->where('app.courseCode', 'CSE703073')
                ->where('app.analyticsUp', false)
                ->has('translations')
                ->where('flash.success', null));
    }

    public function test_translations_are_read_from_lang_json(): void
    {
        $expected = HandleInertiaRequests::translations('vi');
        $file = lang_path('vi.json');
        if (is_file($file)) {
            $this->assertSame(json_decode(file_get_contents($file), true), $expected);
        } else {
            $this->assertSame([], $expected);
        }
    }

    public function test_content_is_localized_with_fallback(): void
    {
        $site = HeritageSite::factory()->create();
        // Xóa bản tiếng Anh: trang /en phải dự phòng về tiếng Việt thay vì lỗi
        $site->interpretations()->where('locale', 'en')->delete();

        $this->get("/en/di-san/{$site->slug}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('site.interpretation.locale', 'vi')
                ->where('site.title', fn ($t) => str_starts_with($t, 'Di tích')));
    }

    public function test_locale_choice_is_saved_to_session_and_user(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get('/en');
        $this->assertSame('en', session('locale'));
        $this->assertSame('en', $user->fresh()->locale);
        $this->get('/')->assertRedirect('/en');
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/vi');

        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('tile.openstreetmap.org', $response->headers->get('Content-Security-Policy'));
    }

    public function test_session_cookie_is_http_only(): void
    {
        $cookie = collect($this->get('/vi')->headers->getCookies())->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }
}
