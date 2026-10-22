<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourSlot extends Model
{
    use HasFactory;

    protected $fillable = ['route_id', 'starts_at', 'ends_at', 'capacity', 'booked_count', 'guide_name', 'language', 'status'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'capacity' => 'integer', 'booked_count' => 'integer'];

    protected $appends = ['available'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(TourRoute::class, 'route_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'slot_id');
    }

    public function getAvailableAttribute(): int
    {
        return max(0, $this->capacity - $this->booked_count);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'open')->where('starts_at', '>', now());
    }
}
