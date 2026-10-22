<?php

namespace App\Http\Resources;

use App\Services\ContentLocalizer;
use App\Services\JsonLdBuilder;
use Illuminate\Http\Request;

class SiteDetailResource extends BaseResource
{
    public int $depth = 1;

    public static function eager(): array
    {
        $depth1 = fn ($q) => $q->where('depth_level', 1);

        return [
            'interpretations', 'media.captions', 'tags.translations', 'province', 'period.translations',
            'figures' => fn ($q) => $q->published(),
            'figures.interpretations' => $depth1, 'figures.media',
            'artifacts' => fn ($q) => $q->published(),
            'artifacts.interpretations' => $depth1, 'artifacts.media.captions', 'artifacts.tags.translations',
            'timelineEvents.translations', 'timelineEvents.period.translations',
            'routeStops.route' => fn ($q) => $q->published(),
            'routeStops.route.translations',
        ];
    }

    public static function detail(mixed $model, int $depth): array
    {
        $resource = new static($model);
        $resource->depth = $depth;

        return $resource->resolve(request());
    }

    public function toArray(Request $request): array
    {
        $site = $this->resource;
        $card = (new SiteCardResource($site))->toArray($request);

        return $card + [
            'address' => $site->address,
            'visiting_hours' => $site->visiting_hours,
            'ticket_price_vnd' => $site->ticket_price_vnd,
            'website' => $site->website,
            'year_recognized' => $site->year_recognized,
            'view_count' => (int) $site->view_count,
            'interpretation' => app(ContentLocalizer::class)->interpretation($site, $this->depth),
            'available_depths' => $site->availableDepths(),
            'media' => $this->mediaGroups($site),
            'figures' => $site->figures->map(fn ($f) => [
                'slug' => $f->slug,
                'title' => $f->title,
                'figure_type' => $f->figure_type,
                'relation_type' => $f->pivot->relation_type,
                'birth_year' => $f->birth_year,
                'death_year' => $f->death_year,
                'portrait' => $f->coverImage()?->url,
                'url' => localized_route('figures.show', ['figure' => $f->slug]),
            ])->values()->all(),
            'artifacts' => ArtifactCardResource::list($site->artifacts->each->setRelation('site', $site)),
            'events' => TimelineItemResource::list($site->timelineEvents->each->setRelation('site', $site)),
            'routes' => $site->routeStops->pluck('route')->filter()->unique('id')->map(fn ($r) => [
                'slug' => $r->slug,
                'name' => $r->name,
                'duration_minutes' => (int) $r->duration_minutes,
                'price_vnd' => (int) $r->price_vnd,
                'url' => localized_route('tours.show', ['route' => $r->slug]),
            ])->values()->all(),
            'jsonld' => app(JsonLdBuilder::class)->site($site),
        ];
    }
}
