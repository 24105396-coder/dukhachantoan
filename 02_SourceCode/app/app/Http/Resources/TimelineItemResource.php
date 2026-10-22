<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TimelineItemResource extends BaseResource
{
    public static function eager(): array
    {
        $depth1 = fn ($q) => $q->where('depth_level', 1);

        return ['translations', 'site.interpretations' => $depth1, 'figure.interpretations' => $depth1, 'period.translations'];
    }

    public function toArray(Request $request): array
    {
        $translation = $this->translate();

        return [
            'id' => $this->id,
            'year' => $this->event_year,
            'month' => $this->event_month,
            'day' => $this->event_day,
            'end_year' => $this->end_year,
            'event_type' => $this->event_type,
            'importance' => (int) $this->importance,
            'title' => $translation?->title ?? '',
            'description' => $translation?->description,
            'subject' => $this->subject(),
            'period' => $this->periodOf($this->relationLoaded('period') ? $this->period : null),
        ];
    }

    private function subject(): ?array
    {
        if ($this->site_id && $this->site) {
            return ['type' => 'site', 'slug' => $this->site->slug, 'title' => $this->site->title,
                'url' => localized_route('sites.show', ['site' => $this->site->slug])];
        }
        if ($this->figure_id && $this->figure) {
            return ['type' => 'figure', 'slug' => $this->figure->slug, 'title' => $this->figure->title,
                'url' => localized_route('figures.show', ['figure' => $this->figure->slug])];
        }

        return null;
    }
}
