<?php

namespace App\Http\Resources;

use App\Models\Destination;
use Illuminate\Http\Request;

/**
 * @mixin Destination
 */
class DestinationCard extends BaseResource
{
    public const RELATIONS = ['translations', 'zone', 'province'];

    public function toArray(Request $request): array
    {
        $d = $this->resource;
        $t = $d->translate();

        return [
            'id' => $d->id,
            'slug' => $d->slug,
            'name' => $t?->name ?? $d->slug,
            'description' => $t?->description,
            'safety_note' => $t?->safety_note,
            'dest_type' => $d->dest_type,
            'zone' => $d->zone?->brief(),
            'province' => $d->province?->brief(),
            'lat' => (float) $d->lat,
            'lng' => (float) $d->lng,
            'is_popular' => (bool) $d->is_popular,
            'url' => $d->zone ? localized_route('weather.show', ['zone' => $d->zone->code]) : null,
        ];
    }
}
