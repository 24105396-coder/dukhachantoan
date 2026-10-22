<?php

namespace App\Http\Resources;

use App\Models\AlertMatch;
use App\Models\TripLeg;
use Illuminate\Http\Request;

/**
 * LegItem: chặng + vùng (ZoneCard) + cảnh báo đang hiệu lực đã đối sánh (khi quan hệ matches đã nạp).
 *
 * @mixin TripLeg
 */
class LegItem extends BaseResource
{
    public function toArray(Request $request): array
    {
        $leg = $this->resource;
        $alerts = $leg->relationLoaded('matches')
            ? $leg->matches->map(fn (AlertMatch $m) => $m->alert)->filter(fn ($a) => $a && $a->isLive())
                ->sortByDesc('severity')->values()
            : collect();
        [$lat, $lng] = $leg->position();

        return [
            'id' => $leg->id,
            'leg_order' => (int) $leg->leg_order,
            'title' => $leg->title,
            'zone' => ZoneCard::one($leg->zone),
            'destination' => DestinationCard::one($leg->destination),
            'starts_at' => self::iso($leg->starts_at),
            'ends_at' => self::iso($leg->ends_at),
            'lodging' => $leg->lodging,
            'transport' => $leg->transport,
            'lat' => $lat,
            'lng' => $lng,
            'note' => $leg->note,
            'risk_level' => (int) ($alerts->max('severity') ?? 0),
            'alerts' => AlertCard::list($alerts),
        ];
    }
}
