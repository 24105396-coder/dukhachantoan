<?php

namespace App\Support;

/**
 * Chuyển factors thô của dịch vụ Python ({weather, alerts, trend, formula, explain}) thành danh sách
 * [{code, label, value, note}] đã địa phương hóa để giao diện hiển thị (page-contract cho phép dạng mảng).
 * Dữ liệu đã ở dạng mảng danh sách thì giữ nguyên.
 */
final class RiskFactors
{
    public static function present(mixed $factors): ?array
    {
        if (! is_array($factors) || $factors === []) {
            return null;
        }
        if (array_is_list($factors)) {
            return $factors;
        }
        $rows = array_values(array_filter([
            self::weather($factors['weather'] ?? null),
            self::alerts($factors['alerts'] ?? null),
            self::trend($factors['trend'] ?? null),
        ]));
        $explain = $factors['explain'][app()->getLocale()] ?? $factors['explain']['vi'] ?? null;
        if (is_string($explain) && $explain !== '') {
            $rows[] = ['code' => 'explain', 'label' => $explain, 'value' => null, 'note' => ''];
        }

        return $rows === [] ? null : $rows;
    }

    private static function weather(mixed $w): ?array
    {
        if (! is_array($w) || ($w['available'] ?? true) === false) {
            return null;
        }
        $in = (array) ($w['inputs'] ?? []);
        $parts = array_filter([
            isset($in['gust_kph']) ? __('risk.factor.gust').' '.self::num($in['gust_kph']).' km/h' : null,
            isset($in['rain_mm']) ? __('risk.factor.rain').' '.self::num($in['rain_mm']).' mm' : null,
            isset($in['temp_c']) ? __('risk.factor.temp').' '.self::num($in['temp_c']).' °C' : null,
        ]);

        return ['code' => 'weather', 'label' => __('risk.factor.weather'), 'value' => self::round($w['score'] ?? null), 'note' => implode(' · ', $parts)];
    }

    private static function alerts(mixed $a): ?array
    {
        if (! is_array($a)) {
            return null;
        }

        return ['code' => 'alerts', 'label' => __('risk.factor.alerts'), 'value' => self::round($a['score'] ?? null),
            'note' => __('risk.active_alerts', ['n' => (int) ($a['count'] ?? 0)])];
    }

    private static function trend(mixed $t): ?array
    {
        if (! is_array($t) || ! isset($t['direction'])) {
            return null;
        }
        $delta = isset($t['delta']) && is_numeric($t['delta']) ? (float) $t['delta'] : null;

        return ['code' => 'trend', 'label' => __('risk.factor.trend'),
            'value' => $delta === null ? null : ($delta > 0 ? '+' : '').self::num($delta),
            'note' => __('risk.trend.'.$t['direction'])];
    }

    private static function round(mixed $v): ?float
    {
        return is_numeric($v) ? round((float) $v, 1) : null;
    }

    private static function num(mixed $v): string
    {
        $f = round((float) $v, 1);

        return rtrim(rtrim(number_format($f, 1, app()->getLocale() === 'vi' ? ',' : '.', ''), '0'), ',.');
    }
}
