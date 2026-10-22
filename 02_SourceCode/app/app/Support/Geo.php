<?php

namespace App\Support;

/**
 * Hình học phẳng đơn giản cho GeoJSON (tọa độ [lng, lat]): haversine và point-in-polygon (ray casting).
 * Đủ chính xác cho vùng cấp tỉnh; dịch vụ Python (shapely) là đường chính, đây là dự phòng.
 */
final class Geo
{
    public const EARTH_RADIUS_KM = 6371.0;

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    /** Hỗ trợ Polygon, MultiPolygon, Circle {center: [lng,lat]|{lat,lng}, radius_km}. */
    public static function contains(array $geometry, float $lat, float $lng): bool
    {
        return match ($geometry['type'] ?? null) {
            'Polygon' => self::inPolygon($geometry['coordinates'] ?? [], $lat, $lng),
            'MultiPolygon' => collect($geometry['coordinates'] ?? [])->contains(fn ($poly) => self::inPolygon($poly, $lat, $lng)),
            'Circle' => self::inCircle($geometry, $lat, $lng),
            default => false,
        };
    }

    /** Polygon = [vòng ngoài, lỗ 1, lỗ 2…]; trong vòng ngoài và không trong lỗ nào. */
    public static function inPolygon(array $rings, float $lat, float $lng): bool
    {
        if ($rings === [] || ! self::inRing($rings[0], $lat, $lng)) {
            return false;
        }
        foreach (array_slice($rings, 1) as $hole) {
            if (self::inRing($hole, $lat, $lng)) {
                return false;
            }
        }

        return true;
    }

    private static function inRing(array $ring, float $lat, float $lng): bool
    {
        $inside = false;
        $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = [(float) $ring[$i][0], (float) $ring[$i][1]];
            [$xj, $yj] = [(float) $ring[$j][0], (float) $ring[$j][1]];
            $crosses = ($yi > $lat) !== ($yj > $lat)
                && $lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi;
            if ($crosses) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function inCircle(array $geometry, float $lat, float $lng): bool
    {
        [$cLng, $cLat] = self::lngLat($geometry['center'] ?? []);
        $radius = (float) ($geometry['radius_km'] ?? 0);

        return $radius > 0 && self::haversineKm($cLat, $cLng, $lat, $lng) <= $radius;
    }

    /** Chuẩn hóa tâm [lng, lat] hoặc {lat, lng} về [lng, lat]. */
    public static function lngLat(array $point): array
    {
        if (array_key_exists('lat', $point)) {
            return [(float) $point['lng'], (float) $point['lat']];
        }

        return [(float) ($point[0] ?? 0), (float) ($point[1] ?? 0)];
    }
}
