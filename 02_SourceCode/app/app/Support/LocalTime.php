<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Chuỗi giờ từ form/API (có thể kèm offset hoặc Z) → giờ địa phương APP_TIMEZONE để lưu cột timestamp. */
final class LocalTime
{
    public static function parse(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
