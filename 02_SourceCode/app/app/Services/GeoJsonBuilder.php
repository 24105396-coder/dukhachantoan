<?php

namespace App\Services;

use Illuminate\Support\Collection;

/** Xây GeoJSON FeatureCollection (tọa độ [lng, lat]) cho bản đồ Leaflet và API. */
class GeoJsonBuilder
{
    /**
     * @param  Collection|array  $items  phần tử bất kỳ
     * @param  callable  $geometry  fn($item): ?array GeoJSON geometry (null = bỏ qua)
     * @param  callable  $properties  fn($item): array
     */
    public function collection(Collection|array $items, callable $geometry, callable $properties): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => collect($items)
                ->map(fn ($item) => $this->feature($geometry($item), $properties($item)))
                ->filter()->values()->all(),
        ];
    }

    public function feature(?array $geometry, array $properties): ?array
    {
        return $geometry === null ? null : ['type' => 'Feature', 'geometry' => $geometry, 'properties' => $properties];
    }

    public static function point(float $lat, float $lng): array
    {
        return ['type' => 'Point', 'coordinates' => [$lng, $lat]];
    }

    public static function empty(): array
    {
        return ['type' => 'FeatureCollection', 'features' => []];
    }
}
