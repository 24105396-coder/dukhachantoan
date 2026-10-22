<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourRouteStop extends Model
{
    public $timestamps = false;
    protected $fillable = ['route_id', 'stop_order', 'site_id', 'artifact_id', 'dwell_minutes', 'note'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(TourRoute::class, 'route_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(HeritageSite::class, 'site_id');
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class, 'artifact_id');
    }
}
