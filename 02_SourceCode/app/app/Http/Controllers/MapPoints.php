<?php

namespace App\Http\Controllers;

use App\Models\HeritageSite;
use Illuminate\Database\Eloquent\Builder;

/** Điểm bản đồ gọn nhẹ: [{slug, title, lat, lng, site_type, url}]. */
final class MapPoints
{
    public static function all(?Builder $query = null): array
    {
        $query ??= HeritageSite::published();

        return $query->with(['interpretations' => fn ($q) => $q->where('depth_level', 1)->select('id', 'subject_type', 'subject_id', 'locale', 'depth_level', 'title')])
            ->get(['id', 'slug', 'lat', 'lng', 'site_type', 'recognition_level'])
            ->map(fn (HeritageSite $s) => [
                'slug' => $s->slug,
                'title' => $s->title,
                'lat' => (float) $s->lat,
                'lng' => (float) $s->lng,
                'site_type' => $s->site_type,
                'recognition_level' => $s->recognition_level,
                'url' => localized_route('sites.show', ['site' => $s->slug]),
            ])->all();
    }
}
