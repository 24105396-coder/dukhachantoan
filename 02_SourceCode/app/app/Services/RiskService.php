<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\RiskScore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Tổng hợp mức rủi ro. Pha 1: tóm tắt toàn quốc cho thanh trạng thái đầu trang (an toàn khi chưa có dữ liệu).
 */
class RiskService
{
    public const SUMMARY_CACHE_KEY = 'risk:national-summary';
    public const SUMMARY_TTL_SECONDS = 60;
    /** Điểm rủi ro cũ hơn số giờ này không tính vào mức toàn quốc. */
    public const SCORE_FRESH_HOURS = 24;

    /** {national_level 0..4, active_alerts, updated_at|null}; lỗi CSDL → mức 0, không chặn trang. */
    public function nationalSummary(): array
    {
        try {
            return Cache::remember(self::SUMMARY_CACHE_KEY, self::SUMMARY_TTL_SECONDS, fn () => $this->computeSummary());
        } catch (Throwable $e) {
            report($e);

            return ['national_level' => 0, 'active_alerts' => 0, 'updated_at' => null];
        }
    }

    public function forgetSummary(): void
    {
        Cache::forget(self::SUMMARY_CACHE_KEY);
    }

    private function computeSummary(): array
    {
        $live = Alert::query()->live();
        $activeAlerts = (clone $live)->count();
        $maxSeverity = (int) ((clone $live)->max('severity') ?? 0);
        $lastAlertUpdate = (clone $live)->max('updated_at');

        $fresh = RiskScore::query()->where('computed_at', '>=', now()->subHours(self::SCORE_FRESH_HOURS));
        $maxRisk = (int) ((clone $fresh)->max('level') ?? 0);
        $lastRisk = (clone $fresh)->max('computed_at');

        $updatedAt = collect([$lastAlertUpdate, $lastRisk])->filter()->max();

        return [
            'national_level' => max($maxSeverity, $maxRisk),
            'active_alerts' => $activeAlerts,
            'updated_at' => $updatedAt ? Carbon::parse($updatedAt)->toIso8601String() : null,
        ];
    }

    /** Mức 0..4 → nhãn đã dịch. */
    public static function levelLabel(int $level): string
    {
        return __('enums.levels.'.max(0, min(4, $level)));
    }
}
