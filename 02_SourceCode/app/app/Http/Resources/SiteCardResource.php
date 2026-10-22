<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SiteCardResource extends BaseResource
{
    /** Quan hệ cần nạp trước cho thẻ di sản (tránh N+1). */
    public static function eager(): array
    {
        return [
            'interpretations' => fn ($q) => $q->where('depth_level', 1),
            'media.captions', 'tags.translations', 'province', 'period.translations',
        ];
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'site_type' => $this->site_type,
            'recognition_level' => $this->recognition_level,
            'province' => $this->provinceOf($this->province),
            'period' => $this->periodOf($this->period),
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'cover' => $this->coverOf($this->resource),
            'tags' => $this->tagsOf($this->resource),
            'year_built' => $this->year_built,
            'url' => localized_route('sites.show', ['site' => $this->slug]),
        ];
    }
}
