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
            ->assertJsonStructure(['status', 'time', 'db', 'analytics', 'version']);
    }

    public function test_healthz_reports_analytics_ok_when_service_answers(): void
    {
        Http::fake(['*/healthz' => Http::response(['status' => 'ok', 'index_size' => 10])]);

        $this->getJson('/healthz')->assertOk()->assertJson(['analytics' => 'ok']);
    }

    public function test_healthz_returns_503_when_database_is_unreachable(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);
        config(['database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => 1]);
        DB::purge('mysql');

        $this->getJson('/healthz')->assertStatus(503)->assertJson(['status' => 'fail', 'db' => 'fail']);
    }
}
