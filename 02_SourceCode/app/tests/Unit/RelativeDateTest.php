<?php

namespace Tests\Unit;

use App\Support\RelativeDate;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RelativeDateTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::create(2026, 10, 22, 14, 30, 0, 'Asia/Ho_Chi_Minh');
    }

    public function test_days_with_time(): void
    {
        $this->assertSame('2026-10-24 08:00', RelativeDate::parse('+2d 08:00', $this->now)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-21 17:00', RelativeDate::parse('-1d 17:00', $this->now)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-22 05:00', RelativeDate::parse('-0d 05:00', $this->now)->format('Y-m-d H:i'));
    }

    public function test_days_without_time_keep_current_clock(): void
    {
        $this->assertSame('2026-09-22 14:30', RelativeDate::parse('-30d', $this->now)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-22', RelativeDate::date('+0d', $this->now));
    }

    public function test_hours_and_minutes(): void
    {
        $this->assertSame('2026-10-22 17:30', RelativeDate::parse('+3h', $this->now)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-22 13:45', RelativeDate::parse('-45m', $this->now)->format('Y-m-d H:i'));
    }

    public function test_absolute_and_empty_values(): void
    {
        $this->assertSame('2024-09-07', RelativeDate::parse('2024-09-07T10:00:00+07:00', $this->now)->toDateString());
        $this->assertNull(RelativeDate::parse(null));
        $this->assertNull(RelativeDate::parse('  '));
        $this->expectException(InvalidArgumentException::class);
        RelativeDate::parse('ngày mai');
    }

    public function test_input_now_is_not_mutated(): void
    {
        RelativeDate::parse('+5d 01:00', $this->now);

        $this->assertSame('2026-10-22 14:30', $this->now->format('Y-m-d H:i'));
    }
}
