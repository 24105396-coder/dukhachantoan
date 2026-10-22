<?php

namespace App\Http\Resources;

use App\Models\Alert;
use App\Models\Zone;
use Illuminate\Http\Request;

/**
 * AlertCard: thẻ cảnh báo dùng chung web + API; luôn kèm nguồn và thời điểm cập nhật.
 *
 * @mixin Alert
 */
class AlertCard extends BaseResource
{
    /** Quan hệ cần nạp trước để tránh N+1. */
    public const RELATIONS = ['translations', 'hazardType.translations', 'zones', 'source', 'scenario'];

    public function toArray(Request $request): array
    {
        $alert = $this->resource;
        $translation = $alert->translate();

        return [
            'id' => $alert->id,
            'code' => $alert->code,
            'severity' => (int) $alert->severity,
            'severity_label' => Alert::severityLabel((int) $alert->severity),
            'status' => $alert->status,
            'status_label' => __('enums.alert_statuses.'.$alert->status),
            'hazard' => $alert->hazardType?->brief(),
            'headline' => $translation?->headline ?? $alert->code,
            'description' => $translation?->description,
            'instructions' => $translation?->instructions,
            'zones' => $alert->zones->map(fn (Zone $z) => $z->brief())->values()->all(),
            'geometry' => $alert->geometry,
            'starts_at' => self::iso($alert->starts_at),
            'ends_at' => self::iso($alert->ends_at),
            'issued_at' => self::iso($alert->issued_at),
            'updated_at_source' => self::iso($alert->updated_at_source ?? $alert->updated_at),
            'source' => [
                'name' => $alert->source_name,
                'url' => $alert->source_url,
                'attribution' => $alert->source?->attribution,
            ],
            'is_simulated' => (bool) $alert->is_simulated,
            'is_live' => $alert->isLive(),
            'scenario' => $alert->scenario ? [
                'slug' => $alert->scenario->slug,
                'name' => $alert->scenario->localized('name'),
            ] : null,
            'url' => localized_route('alerts.show', ['alert' => $alert->code]),
        ];
    }
}
