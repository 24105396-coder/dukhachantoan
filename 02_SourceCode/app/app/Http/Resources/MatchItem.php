<?php

namespace App\Http\Resources;

use App\Models\AlertMatch;
use Illuminate\Http\Request;

/**
 * @mixin AlertMatch
 */
class MatchItem extends BaseResource
{
    public function toArray(Request $request): array
    {
        $m = $this->resource;

        return [
            'id' => $m->id,
            'alert' => AlertCard::one($m->alert),
            'leg' => $m->leg ? ['id' => $m->leg->id, 'leg_order' => (int) $m->leg->leg_order, 'title' => $m->leg->title] : null,
            'trip' => $m->relationLoaded('trip') && $m->trip ? ['code' => $m->trip->code, 'title' => $m->trip->title] : null,
            'match_type' => $m->match_type,
            'match_type_label' => __('enums.match_types.'.$m->match_type),
            'distance_km' => self::km($m->distance_km),
            'overlap_from' => self::iso($m->overlap_from),
            'overlap_to' => self::iso($m->overlap_to),
            'status' => $m->status,
            'acknowledged_at' => self::iso($m->acknowledged_at),
        ];
    }
}
