<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Nhà cung cấp bản đồ nền (Google Maps mặc định, OSM dự phòng) được chia sẻ cho frontend và CSP cho phép tile tương ứng. */
class MapProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_shares_google_as_default_map_provider(): void
    {
        config(['disanso.map.provider' => 'google']);

        $this->get('/vi/ban-do')->assertOk()->assertInertia(fn ($page) => $page->where('map.provider', 'google'));
    }

    public function test_falls_back_to_osm_when_configured(): void
    {
        config(['disanso.map.provider' => 'osm']);

        $this->get('/vi/ban-do')->assertOk()->assertInertia(fn ($page) => $page->where('map.provider', 'osm'));
    }

    public function test_unknown_provider_is_normalized_to_google(): void
    {
        config(['disanso.map.provider' => 'bing']);

        $this->get('/vi')->assertInertia(fn ($page) => $page->where('map.provider', 'google'));
    }

    public function test_csp_allows_google_and_osm_tiles(): void
    {
        $csp = (string) $this->get('/vi')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://*.google.com', $csp);
        $this->assertStringContainsString('tile.openstreetmap.org', $csp);
    }
}
