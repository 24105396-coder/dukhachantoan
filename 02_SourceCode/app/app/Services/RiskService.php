<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertMatch;
use App\Models\RiskScore;
use App\Models\Trip;
use App\Models\TripLeg;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tổng hợp mức rủi ro: theo vùng (điểm Python + cảnh báo hiệu lực), toàn quốc (thanh trạng thái đầu trang),
 * theo hành trình (risk_level_cached từ các đối sánh còn hiệu lực).
 */
class RiskService
{
    public const SUMMARY_CACHE_KEY = 'risk:national-summary';
    public const ZONE_STATS_CACHE_KEY = 'risk:zone-stats';
    public const SUMMARY_TTL_SECONDS = 60;
    /** Điểm rủi ro cũ hơn số giờ này không tính. */
    public const SCORE_FRESH_HOURS = 24;
    /** Điểm quy đổi từ mức cảnh báo khi vùng chưa có điểm Python. */
    public const SCORE_PER_LEVEL = 25;

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

    /** Xóa bộ đệm sau khi cảnh báo/điểm rủi ro thay đổi. */
    public function forgetSummary(): void
    {
        Cache::forget(self::SUMMARY_CACHE_KEY);
        Cache::forget(self::ZONE_STATS_CACHE_KEY);
    }

    /**
     * Bảng tổng hợp theo vùng: zone_id => {score, level, computed_at, factors, active_alerts}.
     * level = max(mức điểm Python còn mới, mức cảnh báo hiệu lực cao nhất gắn với vùng).
     */
    public function zoneStats(): array
    {
        try {
            return Cache::remember(self::ZONE_STATS_CACHE_KEY, self::SUMMARY_TTL_SECONDS, fn () => $this->computeZoneStats());
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Mức rủi ro hành trình = mức cao nhất của cảnh báo hiệu lực đã đối sánh (chưa xử lý xong). */
    public function refreshTrip(Trip $trip): int
    {
        $level = (int) (AlertMatch::query()->where('alert_matches.trip_id', $trip->id)
            ->where('alert_matches.status', '!=', AlertMatch::STATUS_RESOLVED)
            ->join('alerts', 'alerts.id', '=', 'alert_matches.alert_id')
            ->whereIn('alerts.status', Alert::LIVE_STATUSES)
            ->where(fn ($q) => $q->whereNull('alerts.ends_at')->orWhere('alerts.ends_at', '>', now()))
            ->max('alerts.severity') ?? 0);
        if ($trip->status === Trip::STATUS_CANCELLED) {
            $level = 0;
        }
        if ((int) $trip->risk_level_cached !== $level) {
            $trip->forceFill(['risk_level_cached' => $level])->saveQuietly();
        }

        return $level;
    }

    /** Tính lại cho nhiều hành trình theo id. */
    public function refreshTrips(iterable $tripIds): void
    {
        $ids = collect($tripIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }
        Trip::query()->whereIn('id', $ids)->get()->each(fn (Trip $trip) => $this->refreshTrip($trip));
    }

    private function computeSummary(): array
    {
        $stats = collect($this->computeZoneStats());
        $live = Alert::query()->live();
        $maxSeverity = (int) ((clone $live)->max('severity') ?? 0);
        $maxLevel = max($maxSeverity, (int) ($stats->pluck('level')->max() ?? 0));
        $tripZoneIds = TripLeg::query()->whereHas('trip', fn ($q) => $q->ongoing())->distinct()->pluck('zone_id')->all();

        // Mức toàn quốc = mức cao nhất của các vùng đang có hành trình; chưa có hành trình nào → mức cao nhất mọi vùng
        $levels = $stats->only($tripZoneIds)->pluck('level');
        $national = $tripZoneIds !== [] ? (int) ($levels->max() ?? 0) : $maxLevel;

        $lastAlert = (clone $live)->max('updated_at');
        $lastRisk = RiskScore::query()->max('computed_at');
        $updatedAt = collect([$lastAlert, $lastRisk])->filter()->max();

        return [
            'national_level' => $national,
            'active_alerts' => (clone $live)->count(),
            'updated_at' => $updatedAt ? Carbon::parse($updatedAt)->toIso8601String() : null,
        ];
    }

    private function computeZoneStats(): array
    {
        $stats = [];
        foreach ($this->latestScores() as $row) {
            $stats[(int) $row->zone_id] = [
                'score' => (int) $row->score,
                'level' => (int) $row->level,
                'computed_at' => Carbon::parse($row->computed_at)->toIso8601String(),
                'factors' => is_string($row->factors) ? json_decode($row->factors, true) : $row->factors,
                'active_alerts' => 0,
            ];
        }
        foreach ($this->liveAlertsByZone() as $row) {
            $zoneId = (int) $row->zone_id;
            $severity = (int) $row->max_severity;
            $current = $stats[$zoneId] ?? ['score' => null, 'level' => 0, 'computed_at' => null, 'factors' => null, 'active_alerts' => 0];
            $current['active_alerts'] = (int) $row->total;
            $current['level'] = max($current['level'], $severity);
            $current['score'] = max((int) ($current['score'] ?? 0), $severity * self::SCORE_PER_LEVEL);
            $stats[$zoneId] = $current;
        }

        return $stats;
    }

    /** Điểm mới nhất mỗi vùng trong SCORE_FRESH_HOURS giờ. */
    private function latestScores()
    {
        $latest = DB::table('risk_scores')->select('zone_id', DB::raw('MAX(computed_at) AS latest_at'))
            ->where('computed_at', '>=', now()->subHours(self::SCORE_FRESH_HOURS))->groupBy('zone_id');

        return DB::table('risk_scores as rs')
            ->joinSub($latest, 'x', fn ($j) => $j->on('x.zone_id', '=', 'rs.zone_id')->on('x.latest_at', '=', 'rs.computed_at'))
            ->get(['rs.zone_id', 'rs.score', 'rs.level', 'rs.computed_at', 'rs.factors']);
    }

    /** Số cảnh báo hiệu lực và mức cao nhất theo vùng (qua bảng nối alert_zones). */
    private function liveAlertsByZone()
    {
        return DB::table('alert_zones')->join('alerts', 'alerts.id', '=', 'alert_zones.alert_id')
            ->whereIn('alerts.status', Alert::LIVE_STATUSES)
            ->where(fn ($q) => $q->whereNull('alerts.ends_at')->orWhere('alerts.ends_at', '>', now()))
            ->groupBy('alert_zones.zone_id')
            ->get(['alert_zones.zone_id', DB::raw('COUNT(DISTINCT alerts.id) AS total'), DB::raw('MAX(alerts.severity) AS max_severity')]);
    }

    /** Mức 0..4 → nhãn đã dịch. */
    public static function levelLabel(int $level): string
    {
        return __('enums.levels.'.max(0, min(4, $level)));
    }
}
