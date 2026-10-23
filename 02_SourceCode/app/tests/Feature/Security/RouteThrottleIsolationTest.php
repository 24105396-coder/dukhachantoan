<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BUG-8: mỗi route giới hạn tần suất có bộ đếm riêng; dùng hết lượt báo sự cố không khóa tạo hành trình. */
class RouteThrottleIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const INCIDENT_LIMIT = 10;

    private const TRIP_LIMIT = 20;

    public function test_incident_limit_does_not_consume_trip_creation_counter(): void
    {
        $traveler = $this->traveler();
        for ($i = 0; $i < self::INCIDENT_LIMIT; $i++) {
            $this->actingAs($traveler)->postJson('/vi/bao-su-co', [])->assertStatus(422);
        }
        $this->actingAs($traveler)->postJson('/vi/bao-su-co', [])->assertStatus(429);

        $response = $this->actingAs($traveler)->postJson('/vi/hanh-trinh/tao', []);

        $response->assertStatus(422);
        $response->assertHeader('X-RateLimit-Limit', (string) self::TRIP_LIMIT);
        $response->assertHeader('X-RateLimit-Remaining', (string) (self::TRIP_LIMIT - 1));
    }

    public function test_trip_limit_does_not_block_incident_report(): void
    {
        $traveler = $this->traveler();
        for ($i = 0; $i < self::TRIP_LIMIT; $i++) {
            $this->actingAs($traveler)->postJson('/vi/hanh-trinh/tao', [])->assertStatus(422);
        }
        $this->actingAs($traveler)->postJson('/vi/hanh-trinh/tao', [])->assertStatus(429);

        $this->actingAs($traveler)->postJson('/vi/bao-su-co', [])
            ->assertStatus(422)
            ->assertHeader('X-RateLimit-Remaining', (string) (self::INCIDENT_LIMIT - 1));
    }
}
