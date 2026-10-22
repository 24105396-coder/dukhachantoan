<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class FigureCardResource extends BaseResource
{
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
            'figure_type' => $this->figure_type,
            'birth_year' => $this->birth_year,
            'death_year' => $this->death_year,
            'period' => $this->periodOf($this->period),
            'province' => $this->provinceOf($this->province),
            'portrait' => $this->coverImage()?->url,
            'tags' => $this->tagsOf($this->resource),
            'url' => localized_route('figures.show', ['figure' => $this->slug]),
        ];
    }
}
