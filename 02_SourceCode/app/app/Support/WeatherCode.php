<?php

namespace App\Support;

/** Mã thời tiết WMO (Open-Meteo) → nhãn đã dịch và tên icon giao diện. */
final class WeatherCode
{
    /** Nhóm mã → [khóa nhãn, icon]. */
    private const GROUPS = [
        'clear' => [[0], 'sun'],
        'partly_cloudy' => [[1, 2], 'cloud-sun'],
        'overcast' => [[3], 'cloud'],
        'fog' => [[45, 48], 'cloud-fog'],
        'drizzle' => [[51, 53, 55, 56, 57], 'cloud-drizzle'],
        'rain' => [[61, 63, 66, 80, 81], 'cloud-rain'],
        'heavy_rain' => [[65, 67, 82], 'heavy-rain'],
        'snow' => [[71, 73, 75, 77, 85, 86], 'cloud-snow'],
        'thunderstorm' => [[95, 96, 99], 'cloud-lightning'],
    ];

    public static function group(?int $code): ?string
    {
        if ($code === null) {
            return null;
        }
        foreach (self::GROUPS as $group => [$codes]) {
            if (in_array($code, $codes, true)) {
                return $group;
            }
        }

        return 'overcast';
    }

    public static function label(?int $code): ?string
    {
        $group = self::group($code);

        return $group ? __('enums.weather.'.$group) : null;
    }

    public static function icon(?int $code): string
    {
        $group = self::group($code);

        return $group ? self::GROUPS[$group][1] : 'cloud-sun';
    }
}
