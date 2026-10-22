<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ArtifactCardResource extends BaseResource
{
    public static function eager(): array
    {
        return [
            'interpretations' => fn ($q) => $q->where('depth_level', 1),
            'media.captions', 'tags.translations',
            'site.interpretations' => fn ($q) => $q->where('depth_level', 1),
        ];
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'artifact_type' => $this->artifact_type,
            'material' => $this->material,
            'year_from' => $this->year_from,
            'year_to' => $this->year_to,
            'is_national_treasure' => (bool) $this->is_national_treasure,
            'site' => $this->site ? [
                'slug' => $this->site->slug,
                'title' => $this->site->title,
                'url' => localized_route('sites.show', ['site' => $this->site->slug]),
            ] : null,
            'cover' => $this->coverOf($this->resource),
            'tags' => $this->tagsOf($this->resource),
            'url' => localized_route('artifacts.show', ['artifact' => $this->slug]),
        ];
    }
}
