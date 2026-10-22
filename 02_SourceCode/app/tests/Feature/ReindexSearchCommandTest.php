<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** qa_r2_1: lệnh app:reindex-search gọi POST /reindex kèm token, chạy theo lịch 10 phút. */
class ReindexSearchCommandTest extends TestCase
{
    public function test_command_posts_reindex_with_token(): void
    {
        Http::fake(['*/reindex' => Http::response(['indexed' => 453, 'took_ms' => 120])]);

        $this->artisan('app:reindex-search')->expectsOutputToContain('indexed=453')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/reindex')
            && $r->hasHeader('X-Analytics-Token', 'test-token'));
    }

    public function test_command_fails_gracefully_when_service_down(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);

        $this->artisan('app:reindex-search')->assertFailed();
    }

    public function test_command_is_scheduled_every_ten_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'app:reindex-search'));

        $this->assertNotNull($event);
        $this->assertSame('*/10 * * * *', $event->expression);
    }
}
