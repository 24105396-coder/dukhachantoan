<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class RouteCardResource extends BaseResource
{
    public static function eager(): array
    {
        return [
            'translations', 'media.captions', 'province', 'themeTag.translations',
            'slots' => fn ($q) => $q->upcoming(),
        ];
    }

    public function toArray(Request $request): array
    {
        $translation = $this->translate();
        $nextSlot = $this->relationLoaded('slots')
            ? $this->slots->first(fn ($s) => $s->status === 'open' && $s->starts_at->isFuture() && $s->available > 0)
            : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $translation?->name ?? $this->slug,
            'description' => $translation?->description,
            'highlights' => $translation?->highlights,
            'duration_minutes' => (int) $this->duration_minutes,
            'distance_km' => $this->distance_km,
            'difficulty' => $this->difficulty,
            'price_vnd' => (int) $this->price_vnd,
            'province' => $this->provinceOf($this->province),
            'theme' => $this->themeTag ? ['slug' => $this->themeTag->slug, 'name' => $this->themeTag->name] : null,
            'cover' => $this->coverOf($this->resource),
            'stops_count' => (int) ($this->stops_count ?? ($this->relationLoaded('stops') ? $this->stops->count() : 0)),
            'next_slot' => SlotItemResource::one($nextSlot),
            'status' => $this->status,
            'url' => localized_route('tours.show', ['route' => $this->slug]),
        ];
    }
}
