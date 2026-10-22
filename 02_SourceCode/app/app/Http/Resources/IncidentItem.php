<?php

namespace App\Http\Resources;

use App\Models\IncidentReport;
use Illuminate\Http\Request;

/**
 * @mixin IncidentReport
 */
class IncidentItem extends BaseResource
{
    public const RELATIONS = ['hazardType.translations', 'zone', 'photo', 'user'];

    public function toArray(Request $request): array
    {
        $i = $this->resource;

        return [
            'id' => $i->id,
            'title' => $i->title,
            'description' => $i->description,
            'hazard' => $i->hazardType?->brief(),
            'zone' => $i->zone?->brief(),
            'lat' => $i->lat,
            'lng' => $i->lng,
            'photo_url' => $i->photo?->url,
            'status' => $i->status,
            'status_label' => __('enums.incident_statuses.'.$i->status),
            'review_note' => $i->review_note,
            'reviewed_at' => self::iso($i->reviewed_at),
            'created_at' => self::iso($i->created_at),
            'reporter' => $i->user ? ['name' => $i->user->name] : null,
            'is_simulated' => (bool) $i->is_simulated,
        ];
    }
}
