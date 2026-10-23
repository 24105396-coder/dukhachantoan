<?php

namespace Tests\Feature;

use App\Services\AnalyticsClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Không dùng RefreshDatabase: test làm hỏng kết nối CSDL có chủ đích. */
class HealthzTest extends TestCase
{
    public function test_healthz_reports_ok_db_and_down_analytics(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        $this->getJson('/healthz')->assertOk()
            ->assertJson(['status' => 'ok', 'db' => 'ok', 'analytics' => 'down'])
            ->assertJsonStructure(['status', 'time', 'updated_at', 'db', 'analytics', 'queue', 'last_weather_fetch', 'version'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_healthz_reports_analytics_ok_when_service_answers(): void
    {
        Http::fake(['*/healthz' => Http::response(['status' => 'ok', 'db' => 'ok', 'last_fetch' => null])]);

        $this->getJson('/healthz')->assertOk()->assertJson(['analytics' => 'ok']);
    }

    public function test_healthz_returns_503_when_database_is_unreachable(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);
        config(['database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => 1]);
        DB::purge('mysql');

        $this->getJson('/healthz')->assertStatus(503)->assertJson(['status' => 'fail', 'db' => 'fail', 'queue' => null]);
    }

    public function test_healthz_ignores_sixty_second_status_memory(): void
    {
        // Trang khác vừa ghi "up" vào bộ nhớ 60 s, nhưng dịch vụ đã tắt: /healthz phải báo down ngay
        Cache::put(AnalyticsClient::STATUS_KEY, true, 60);
        Cache::forget(AnalyticsClient::PROBE_KEY);
        Http::fake(['*' => Http::response(null, 500)]);

        $this->getJson('/healthz')->assertOk()->assertJson(['analytics' => 'down']);
        $this->assertFalse(Cache::get(AnalyticsClient::STATUS_KEY));
    }

    public function test_healthz_probe_result_is_cached_at_most_five_seconds(): void
    {
        Cache::forget(AnalyticsClient::PROBE_KEY);
        Http::fake(['*/healthz' => Http::sequence()->push(['status' => 'ok'])->push(null, 500)->push(null, 500)]);

        $this->getJson('/healthz')->assertJson(['analytics' => 'ok']);
        $this->getJson('/healthz')->assertJson(['analytics' => 'ok']);
        Http::assertSentCount(1);

        $this->travel(6)->seconds();
        $this->getJson('/healthz')->assertJson(['analytics' => 'down']);
        Http::assertSentCount(2);
    }
}
