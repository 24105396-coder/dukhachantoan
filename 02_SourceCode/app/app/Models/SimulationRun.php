<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lần chạy kịch bản mô phỏng và số liệu sinh ra. */
class SimulationRun extends Model
{
    protected $fillable = [
        'scenario_id', 'started_by', 'started_at', 'finished_at', 'alerts_created', 'matches_created', 'notifications_created', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'alerts_created' => 'integer',
            'matches_created' => 'integer',
            'notifications_created' => 'integer',
            'summary' => 'array',
        ];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(SimulationScenario::class, 'scenario_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
