<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Chặng hành trình: vùng (bắt buộc), điểm đến (tùy chọn), khoảng thời gian. */
class TripLeg extends Model
{
    use HasFactory;

    protected $table = 'trip_legs';

    protected $fillable = [
        'trip_id', 'leg_order', 'zone_id', 'destination_id', 'title', 'starts_at', 'ends_at', 'lodging', 'transport',
        'lat', 'lng', 'note',
    ];

    protected function casts(): array
    {
        return [
            'leg_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(AlertMatch::class, 'leg_id');
    }

    /** Vị trí chặng: tọa độ riêng → điểm đến → tâm vùng. @return array{0: ?float, 1: ?float} */
    public function position(): array
    {
        if ($this->lat !== null && $this->lng !== null) {
            return [(float) $this->lat, (float) $this->lng];
        }
        if ($this->destination) {
            return [(float) $this->destination->lat, (float) $this->destination->lng];
        }

        return $this->zone ? [(float) $this->zone->centroid_lat, (float) $this->zone->centroid_lng] : [null, null];
    }

    /** Chặng có giao khoảng [from, to] (null = không giới hạn). */
    public function scopeOverlapping($query, $from, $to)
    {
        return $query
            ->when($to, fn ($q) => $q->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $to)))
            ->when($from, fn ($q) => $q->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $from)));
    }
}
