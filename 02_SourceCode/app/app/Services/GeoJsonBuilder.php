<?php

namespace App\Services;

use App\Models\HeritageSite;
use Illuminate\Support\Collection;

/** Xây GeoJSON FeatureCollection (tọa độ theo thứ tự [lng, lat]) cho bản đồ Leaflet. */
class GeoJsonBuilder
{
    public function sites(Collection $sites): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $sites->map(fn (HeritageSite $site) => $this->feature($site))->values()->all(),
        ];
    }

    private function feature(HeritageSite $site): array
    {
        $cover = $site->coverImage();

        return [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [(float) $site->lng, (float) $site->lat]],
            'properties' => [
                'slug' => $site->slug,
                'title' => $site->title,
                'summary' => $site->summary,
                'site_type' => $site->site_type,
                'site_type_label' => __('enums.site_types.'.$site->site_type),
                'recognition_level' => $site->recognition_level,
                'province' => $site->province?->localizedName(),
                'province_code' => $site->province?->code,
                'url' => localized_route('sites.show', ['site' => $site->slug]),
                'cover' => $cover?->url,
            ],
        ];
    }
}
