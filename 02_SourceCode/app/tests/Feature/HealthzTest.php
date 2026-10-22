<?php

namespace Tests\Feature;

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
}
