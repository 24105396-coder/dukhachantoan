<?php

namespace App\Http\Resources;

use App\Models\Zone;
use App\Services\RiskService;
use App\Support\RiskFactors;
use Illuminate\Http\Request;

/**
 * ZoneCard: vùng + điểm rủi ro mới nhất + số cảnh báo đang hiệu lực (lấy từ bảng tổng hợp đã đệm của RiskService).
 *
 * @mixin Zone
 */
class ZoneCard extends BaseResource
{
    public function toArray(Request $request): array
    {
        $zone = $this->resource;
        $stats = app(RiskService::class)->zoneStats()[$zone->id] ?? null;

        return [
            'id' => $zone->id,
            'code' => $zone->code,
            'zone_type' => $zone->zone_type,
            'name' => $zone->localizedName(),
            'province' => $zone->province?->brief(),
            'centroid' => ['lat' => (float) $zone->centroid_lat, 'lng' => (float) $zone->centroid_lng],
            'radius_km' => $zone->radius_km !== null ? (float) $zone->radius_km : null,
            'risk' => $stats && $stats['computed_at'] !== null ? [
                'score' => $stats['score'],
                'level' => $stats['level'],
                'computed_at' => $stats['computed_at'],
                'factors' => RiskFactors::present($stats['factors']),
            ] : ($stats && $stats['level'] > 0 ? [
                'score' => $stats['score'], 'level' => $stats['level'], 'computed_at' => null, 'factors' => null,
            ] : null),
            'active_alerts_count' => $stats['active_alerts'] ?? 0,
            'url' => localized_route('weather.show', ['zone' => $zone->code]),
        ];
    }
}
