<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Du khách báo sự cố thực địa; cơ quan duyệt (verified/rejected). */
class IncidentReport extends Model
{
    use HasMedia;

    public const STATUSES = ['pending', 'verified', 'rejected'];

    protected $fillable = [
        'user_id', 'trip_id', 'zone_id', 'hazard_type_id', 'title', 'description', 'lat', 'lng', 'photo_media_id', 'status',
        'reviewed_by', 'review_note', 'reviewed_at', 'is_simulated',
    ];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'reviewed_at' => 'datetime', 'is_simulated' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function hazardType(): BelongsTo
    {
        return $this->belongsTo(HazardType::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
