<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\WeatherObservation;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthzQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthz_reports_queue_counts_and_last_weather_fetch(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);
        Notification::factory()->count(3)->create();
        Notification::factory()->failed()->create();
        Notification::factory()->sent()->create();
        $fetched = now()->subMinutes(12)->startOfSecond();
        WeatherObservation::create(['zone_id' => Zone::factory()->create()->id, 'observed_at' => now(), 'fetched_at' => $fetched]);

        $this->getJson('/healthz')->assertOk()
            ->assertJsonPath('queue.queued', 3)
            ->assertJsonPath('queue.failed', 1)
            ->assertJsonPath('queue.jobs', 0)
            ->assertJsonPath('last_weather_fetch', $fetched->toIso8601String());
    }

    public function test_healthz_last_weather_fetch_is_null_without_data(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        $this->getJson('/api/v1/healthz')->assertOk()->assertJsonPath('last_weather_fetch', null)->assertJsonPath('queue.queued', 0);
    }
}
