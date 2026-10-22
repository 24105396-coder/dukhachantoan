<?php

namespace App\Http\Resources;

use App\Models\SafetyCheckin;
use App\Models\Trip;
use App\Models\TripLeg;
use Illuminate\Http\Request;

/**
 * TripCard: tóm tắt hành trình. Dùng Trip::query()->withCardData() để nạp đủ quan hệ và số cảnh báo.
 *
 * @mixin Trip
 */
class TripCard extends BaseResource
{
    public function toArray(Request $request): array
    {
        $trip = $this->resource;
        $legs = $trip->relationLoaded('legs') ? $trip->legs : $trip->legs()->get();
        $lastCheckin = $trip->relationLoaded('latestCheckin') ? $trip->latestCheckin : null;

        return [
            'id' => $trip->id,
            'code' => $trip->code,
            'title' => $trip->title,
            'start_date' => $trip->start_date?->toDateString(),
            'end_date' => $trip->end_date?->toDateString(),
            'group_size' => (int) $trip->group_size,
            'status' => $trip->status,
            'status_label' => __('enums.trip_statuses.'.$trip->status),
            'risk_level' => (int) $trip->risk_level_cached,
            'legs_count' => $legs->count(),
            'active_alerts_count' => (int) ($trip->active_alerts_count ?? 0),
            'next_leg' => LegItem::one($this->nextLeg($legs)),
            'last_checkin' => $lastCheckin instanceof SafetyCheckin ? CheckinItem::one($lastCheckin) : null,
            'owner' => $trip->user ? ['name' => $trip->user->name] : null,
            'partner' => $trip->partner ? ['name' => $trip->partner->organization ?: $trip->partner->name] : null,
            'last_alert_at' => self::iso($trip->last_alert_at),
            'url' => localized_route('trips.show', ['trip' => $trip->code]),
            'share_url' => localized_route('trips.shared', ['token' => $trip->share_token]),
        ];
    }

    /** Chặng đang diễn ra hoặc sắp tới gần nhất. */
    private function nextLeg($legs): ?TripLeg
    {
        $now = now();

        return $legs->first(fn (TripLeg $leg) => $leg->ends_at === null || $leg->ends_at->gte($now));
    }
}
