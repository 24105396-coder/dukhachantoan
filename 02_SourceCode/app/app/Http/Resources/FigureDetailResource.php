<?php

namespace App\Http\Resources;

use App\Services\ContentLocalizer;
use App\Services\JsonLdBuilder;
use Illuminate\Http\Request;

class FigureDetailResource extends BaseResource
{
    public int $depth = 1;

    public static function eager(): array
    {
        return [
            'interpretations', 'media.captions', 'tags.translations', 'province', 'period.translations',
            'sites' => fn ($q) => $q->published(),
            'sites.interpretations' => fn ($q) => $q->where('depth_level', 1),
            'timelineEvents.translations', 'timelineEvents.period.translations',
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
        $figure = $this->resource;
        $card = (new FigureCardResource($figure))->toArray($request);

        return $card + [
            'interpretation' => app(ContentLocalizer::class)->interpretation($figure, $this->depth),
            'available_depths' => $figure->availableDepths(),
            'media' => $this->mediaGroups($figure),
            'sites' => $figure->sites->map(fn ($s) => [
                'slug' => $s->slug,
                'title' => $s->title,
                'relation_type' => $s->pivot->relation_type,
                'url' => localized_route('sites.show', ['site' => $s->slug]),
            ])->values()->all(),
            'events' => TimelineItemResource::list($figure->timelineEvents->each->setRelation('figure', $figure)),
            'view_count' => (int) $figure->view_count,
            'jsonld' => app(JsonLdBuilder::class)->figure($figure),
        ];
    }
}
