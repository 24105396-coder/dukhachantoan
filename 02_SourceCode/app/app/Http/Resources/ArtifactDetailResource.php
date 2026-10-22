<?php

namespace App\Http\Resources;

use App\Services\ContentLocalizer;
use App\Services\JsonLdBuilder;
use Illuminate\Http\Request;

class ArtifactDetailResource extends BaseResource
{
    public int $depth = 1;

    public static function eager(): array
    {
        return [
            'interpretations', 'media.captions', 'tags.translations', 'period.translations',
            'site.interpretations' => fn ($q) => $q->where('depth_level', 1),
            'site.timelineEvents.translations',
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
        $artifact = $this->resource;
        $card = (new ArtifactCardResource($artifact))->toArray($request);
        $events = $artifact->site?->timelineEvents ?? collect();
        if ($artifact->year_from) {
            // Sự kiện của di tích trong khoảng niên đại hiện vật
            $to = $artifact->year_to ?? $artifact->year_from;
            $events = $events->filter(fn ($e) => $e->event_year >= $artifact->year_from - 50 && $e->event_year <= $to + 50);
        }

        return $card + [
            'inventory_code' => $artifact->inventory_code,
            'height_cm' => $artifact->height_cm,
            'width_cm' => $artifact->width_cm,
            'depth_cm' => $artifact->depth_cm,
            'weight_kg' => $artifact->weight_kg,
            'model3d_url' => $artifact->model3d_url,
            'period' => $this->periodOf($artifact->period),
            'interpretation' => app(ContentLocalizer::class)->interpretation($artifact, $this->depth),
            'available_depths' => $artifact->availableDepths(),
            'media' => $this->mediaGroups($artifact),
            'events' => TimelineItemResource::list($events->each(fn ($e) => $e->setRelation('site', $artifact->site))),
            'view_count' => (int) $artifact->view_count,
            'jsonld' => app(JsonLdBuilder::class)->artifact($artifact),
        ];
    }
}
