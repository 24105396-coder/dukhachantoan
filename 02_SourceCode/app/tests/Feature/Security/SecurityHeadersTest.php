<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** BUG-4: header bảo mật trên phản hồi JSON (/api/v1/*, /healthz); CSP img-src chỉ tile bản đồ thật. */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public static function jsonEndpoints(): array
    {
        return [
            'api alerts' => ['/api/v1/alerts'],
            'api healthz' => ['/api/v1/healthz'],
            'healthz' => ['/healthz'],
            'api 404' => ['/api/v1/alerts/AL-KHONGCO'],
        ];
    }

    #[DataProvider('jsonEndpoints')]
    public function test_json_responses_carry_security_headers(string $url): void
    {
        $response = $this->getJson($url);

        foreach (self::BASE_HEADERS as $name => $value) {
            $response->assertHeader($name, $value);
        }
        $this->assertStringContainsString("default-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_html_csp_img_src_lists_only_osm_tiles(): void
    {
        $csp = (string) $this->get('/vi')->assertOk()->headers->get('Content-Security-Policy');

        preg_match('/img-src ([^;]+)/', $csp, $m);
        $sources = explode(' ', trim($m[1] ?? ''));
        $this->assertNotContains('https:', $sources);
        $this->assertContains('https://tile.openstreetmap.org', $sources);
        $this->assertContains("'self'", $sources);
    }

    public function test_google_tiles_allowed_only_when_provider_is_google(): void
    {
        config(['dukhach.map.provider' => 'google']);

        $csp = (string) $this->get('/vi')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://mt0.google.com', $csp);
        $this->assertStringNotContainsString(' https: ', $csp);
    }
}
