<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertSource;
use App\Models\Notification;
use App\Models\SafetyCheckin;
use App\Models\Trip;
use App\Models\WeatherObservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Số liệu bảng điều khiển; $provinceId != null giới hạn theo tỉnh của cơ quan (vùng của cảnh báo/chặng).
 */
class DashboardService
{
    public function kpis(?int $provinceId): array
    {
        $since = now()->subDay();
        $alerts = $this->alertsInScope($provinceId)->live();
        $bySeverity = (clone $alerts)->selectRaw('severity, COUNT(*) as total')->groupBy('severity')->pluck('total', 'severity');
        $notifications = $this->notificationsInScope($provinceId)->where('created_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $checkins = $this->checkinsInScope($provinceId)->where('checked_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $affected = $this->tripsInScope($provinceId)->ongoing()
            ->whereHas('matches', fn ($q) => $q->whereHas('alert', fn ($a) => $a->live()));

        return [
            'active_alerts' => (clone $alerts)->count(),
            'alerts_by_severity' => collect([1, 2, 3, 4])->mapWithKeys(fn ($s) => [$s => (int) ($bySeverity[$s] ?? 0)])->all(),
            'trips_active' => $this->tripsInScope($provinceId)->ongoing()->count(),
            'trips_in_affected_zones' => (clone $affected)->count(),
            'travelers_affected' => (int) (clone $affected)->sum('group_size'),
            'checkins_need_help_open' => $this->checkinsInScope($provinceId)->where('status', SafetyCheckin::STATUS_NEED_HELP)
                ->where('response_status', '!=', 'closed')->count(),
            'checkins_last_24h' => collect(SafetyCheckin::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($checkins[$s] ?? 0)])->all(),
            'notifications_24h' => collect(['queued', 'sent', 'failed', 'read'])->mapWithKeys(fn ($s) => [$s => (int) ($notifications[$s] ?? 0)])->all(),
            'delivery_success_rate' => $this->successRate($notifications->all()),
            'sources_ok' => AlertSource::query()->where('is_active', true)->where('last_status', 'ok')->count(),
            'last_weather_fetch' => $this->iso(WeatherObservation::query()->max('fetched_at')),
        ];
    }

    /** Tình trạng hàng đợi; $provinceId != null chỉ tính thông báo thuộc tỉnh (admin toàn hệ thống). */
    public function queueHealth(?int $provinceId = null): array
    {
        $queued = $this->notificationsInScope($provinceId)->where('status', Notification::STATUS_QUEUED);

        return [
            'queued' => (clone $queued)->count(),
            'failed' => $this->notificationsInScope($provinceId)->where('status', Notification::STATUS_FAILED)->count(),
            'oldest_queued_at' => $this->iso((clone $queued)->min('created_at')),
        ];
    }

    /** Tỉ lệ gửi thành công = (sent + read) / (sent + read + failed); null khi chưa có lần gửi. */
    private function successRate(array $counts): ?float
    {
        $ok = (int) ($counts['sent'] ?? 0) + (int) ($counts['read'] ?? 0);
        $total = $ok + (int) ($counts['failed'] ?? 0);

        return $total > 0 ? round($ok / $total, 3) : null;
    }

    private function alertsInScope(?int $provinceId): Builder
    {
        return Alert::query()->when($provinceId, fn ($q) => $q->whereHas('zones', fn ($z) => $z->where('province_id', $provinceId)));
    }

    private function tripsInScope(?int $provinceId): Builder
    {
        return Trip::query()->when($provinceId, fn ($q) => $q->whereHas('legs.zone', fn ($z) => $z->where('province_id', $provinceId)));
    }

    private function checkinsInScope(?int $provinceId): Builder
    {
        return SafetyCheckin::query()->when($provinceId, fn ($q) => $q->whereHas('trip.legs.zone', fn ($z) => $z->where('province_id', $provinceId)));
    }

    private function notificationsInScope(?int $provinceId): Builder
    {
        return Notification::query()->when($provinceId, fn ($q, $p) => $q->inProvince($p));
    }

    private function iso(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }
}
