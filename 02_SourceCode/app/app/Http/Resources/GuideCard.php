<?php

namespace App\Http\Resources;

use App\Models\ResponseGuide;
use Illuminate\Http\Request;

/**
 * @mixin ResponseGuide
 */
class GuideCard extends BaseResource
{
    public const RELATIONS = ['translations', 'hazardType.translations'];

    public function toArray(Request $request): array
    {
        $g = $this->resource;
        $t = $g->translate();
        $hazard = $g->hazardType;

        return [
            'id' => $g->id,
            'slug' => $g->slug,
            'title' => $t?->title ?? $g->slug,
            'summary' => $t?->summary,
            'hazard' => $hazard ? ['code' => $hazard->code, 'name' => $hazard->name, 'icon' => $hazard->icon] : null,
            'min_severity' => (int) $g->min_severity,
            'audience' => $g->audience,
            'status' => $g->status,
            'is_machine_translated' => (bool) ($t?->is_machine_translated ?? false),
            'url' => localized_route('guides.show', ['guide' => $g->slug]),
        ];
    }
}
