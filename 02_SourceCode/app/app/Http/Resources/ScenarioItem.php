<?php

namespace App\Http\Resources;

use App\Models\SimulationScenario;
use Illuminate\Http\Request;

/**
 * @mixin SimulationScenario
 */
class ScenarioItem extends BaseResource
{
    public const RELATIONS = ['hazardType.translations', 'lastRun'];

    public function toArray(Request $request): array
    {
        $s = $this->resource;
        $run = $s->lastRun;

        return [
            'id' => $s->id,
            'slug' => $s->slug,
            'name' => $s->localized('name'),
            'description' => $s->localized('description'),
            'hazard' => $s->hazardType?->brief(),
            'severity' => (int) $s->severity,
            'zone_codes' => $s->zone_codes ?? [],
            'duration_hours' => (int) $s->duration_hours,
            'steps_count' => count($s->payload['steps'] ?? []),
            'status' => $s->status,
            'last_run' => $run ? [
                'id' => $run->id,
                'started_at' => self::iso($run->started_at),
                'finished_at' => self::iso($run->finished_at),
                'alerts_created' => (int) $run->alerts_created,
                'matches_created' => (int) $run->matches_created,
                'notifications_created' => (int) $run->notifications_created,
            ] : null,
        ];
    }
}
