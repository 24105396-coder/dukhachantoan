<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Đổi mốc thời gian tương đối trong dữ liệu hạt giống/kịch bản sang Carbon theo now():
 *  "+2d 08:00" → 08:00 hai ngày tới; "-30d" → 30 ngày trước (giữ giờ hiện tại); "+0d" → hôm nay;
 *  "+3h", "-45m" → cộng/trừ giờ, phút. Chuỗi khác được hiểu là ngày giờ tuyệt đối (ISO 8601).
 */
final class RelativeDate
{
    private const PATTERN = '/^([+-])(\d+)([dhm])(?:\s+(\d{1,2}):(\d{2}))?$/';

    public static function parse(?string $value, ?CarbonInterface $now = null): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $now = Carbon::instance($now ?? now());

        if (! preg_match(self::PATTERN, $value, $m)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                throw new InvalidArgumentException("Mốc thời gian không hợp lệ: {$value}");
            }
        }

        $amount = (int) $m[2] * ($m[1] === '-' ? -1 : 1);
        $result = match ($m[3]) {
            'd' => $now->copy()->addDays($amount),
            'h' => $now->copy()->addHours($amount),
            'm' => $now->copy()->addMinutes($amount),
        };

        if (isset($m[4]) && $m[4] !== '') {
            $result->setTime((int) $m[4], (int) $m[5], 0);
        }

        return $result;
    }

    /** Ngày (Y-m-d) của mốc tương đối, dùng cho start_date/end_date. */
    public static function date(?string $value, ?CarbonInterface $now = null): ?string
    {
        return self::parse($value, $now)?->toDateString();
    }
}
