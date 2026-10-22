<?php

namespace App\Models\Concerns;

use App\Support\Geo;

/**
 * Hình học GeoJSON lưu trong cột JSON `geometry` (Polygon, MultiPolygon hoặc {type: Circle, center, radius_km}).
 * Model không có polygon dùng tâm (centroid_lat/lng hoặc lat/lng) + radius_km.
 * Tính toán tại chỗ bằng PHP (dự phòng khi dịch vụ Python tắt); MatchingService ưu tiên gọi Python /geo/contains.
 */
trait HasGeometry
{
    /** GeoJSON geometry dùng cho Leaflet; Circle đổi thành Point kèm radius_km trong properties phía gọi. */
    public function geometryGeoJson(): ?array
    {
        $geometry = $this->geometry;
        if (is_array($geometry) && isset($geometry['type'])) {
            if ($geometry['type'] === 'Circle' && isset($geometry['center'])) {
                return ['type' => 'Point', 'coordinates' => Geo::lngLat($geometry['center'])];
            }

            return $geometry;
        }
        [$lat, $lng] = $this->geometryCenter();

        return $lat === null ? null : ['type' => 'Point', 'coordinates' => [$lng, $lat]];
    }

    public function containsPoint(float $lat, float $lng): bool
    {
        $geometry = $this->geometry;
        if (is_array($geometry) && isset($geometry['type'])) {
            return Geo::contains($geometry, $lat, $lng);
        }
        [$cLat, $cLng] = $this->geometryCenter();
        $radius = (float) ($this->radius_km ?? 0);

        return $cLat !== null && $radius > 0 && Geo::haversineKm($cLat, $cLng, $lat, $lng) <= $radius;
    }

    /** @return array{0: ?float, 1: ?float} */
    public function geometryCenter(): array
    {
        $lat = $this->centroid_lat ?? $this->lat ?? null;
        $lng = $this->centroid_lng ?? $this->lng ?? null;

        return $lat === null || $lng === null ? [null, null] : [(float) $lat, (float) $lng];
    }
}
