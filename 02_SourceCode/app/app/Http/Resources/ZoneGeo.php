<?php

namespace App\Http\Resources;

use App\Models\Zone;
use App\Services\GeoJsonBuilder;
use App\Services\RiskService;
use Illuminate\Support\Collection;

/**
 * ZoneGeo: GeoJSON Feature của vùng, properties {code, name, level, score, active_alerts_count, url}.
 * Vùng không có polygon trả Point kèm radius_km để Leaflet vẽ vòng tròn.
 */
final class ZoneGeo
{
    public static function feature(Zone $zone, array $stats): ?array
    {
        $geometry = is_array($zone->geometry) && isset($zone->geometry['type'])
            ? $zone->geometry
            : GeoJsonBuilder::point((float) $zone->centroid_lat, (float) $zone->centroid_lng);
        $zoneStats = $stats[$zone->id] ?? null;

        return (new GeoJsonBuilder)->feature($geometry, [
            'code' => $zone->code,
            'name' => $zone->localizedName(),
            'zone_type' => $zone->zone_type,
            'level' => $zoneStats['level'] ?? 0,
            'score' => $zoneStats['score'] ?? null,
            'active_alerts_count' => $zoneStats['active_alerts'] ?? 0,
            'radius_km' => $zone->radius_km !== null ? (float) $zone->radius_km : null,
            'url' => localized_route('weather.show', ['zone' => $zone->code]),
        ]);
    }

    /** FeatureCollection từ danh sách vùng. */
    public static function collection(Collection|array $zones): array
    {
        $stats = app(RiskService::class)->zoneStats();

        return [
            'type' => 'FeatureCollection',
            'features' => collect($zones)->map(fn (Zone $z) => self::feature($z, $stats))->filter()->values()->all(),
        ];
    }
}
