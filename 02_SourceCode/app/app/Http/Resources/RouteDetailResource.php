<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class RouteDetailResource extends BaseResource
{
    public static function eager(): array
    {
        return array_merge(RouteCardResource::eager(), [
            'owner',
            'stops.site' => fn ($q) => $q->withTrashed(),
            'stops.site.interpretations' => fn ($q) => $q->where('depth_level', 1),
            'stops.site.media.captions', 'stops.site.tags.translations', 'stops.site.province', 'stops.site.period.translations',
        ]);
    }

    public function toArray(Request $request): array
    {
        $route = $this->resource;
        $card = (new RouteCardResource($route))->toArray($request);
        $card['stops_count'] = $route->stops->count();

        return $card + [
            'stops' => $route->stops->map(fn ($stop) => [
                'stop_order' => (int) $stop->stop_order,
                'dwell_minutes' => (int) $stop->dwell_minutes,
                'note' => $stop->note,
                'site' => SiteCardResource::one($stop->site),
            ])->values()->all(),
            'slots' => SlotItemResource::list($route->slots),
            'owner' => $route->owner ? ['name' => $route->owner->organization ?: $route->owner->name] : null,
        ];
    }
}
