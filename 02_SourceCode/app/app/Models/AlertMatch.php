<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kết quả đối sánh cảnh báo với một chặng hành trình trùng thời gian (F3). */
class AlertMatch extends Model
{
    public const TYPES = ['zone', 'geometry', 'destination'];
    public const STATUS_NEW = 'new';
    public const STATUS_NOTIFIED = 'notified';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'alert_id', 'trip_id', 'leg_id', 'match_type', 'distance_km', 'overlap_from', 'overlap_to', 'status', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'float',
            'overlap_from' => 'datetime',
            'overlap_to' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function leg(): BelongsTo
    {
        return $this->belongsTo(TripLeg::class, 'leg_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'match_id');
    }
}
