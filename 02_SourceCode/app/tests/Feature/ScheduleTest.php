<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/** Lịch nền đăng ký đủ lệnh pha 2; lệnh khung chạy thành công để scheduler không lỗi. */
class ScheduleTest extends TestCase
{
    public const EXPECTED = [
        'app:fetch-weather' => '*/30 * * * *',
        'app:match-alerts' => '*/5 * * * *',
        'app:expire-alerts' => '*/10 * * * *',
        'app:dispatch-notifications' => '* * * * *',
        'app:backup-db' => '0 2 * * *',
    ];

    public function test_schedule_registers_all_background_commands(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($e) => [preg_replace('/^.*artisan.? /', '', $e->command) => $e->expression]);

        foreach (self::EXPECTED as $command => $expression) {
            $key = $events->keys()->first(fn ($k) => str_starts_with($k, $command));
            $this->assertNotNull($key, "Thiếu lịch {$command}");
            $this->assertSame($expression, $events[$key], $command);
        }
    }

    public function test_stub_commands_succeed(): void
    {
        foreach (['app:fetch-weather', 'app:match-alerts', 'app:expire-alerts', 'app:dispatch-notifications'] as $command) {
            $this->artisan($command)->assertSuccessful();
        }
    }
}
