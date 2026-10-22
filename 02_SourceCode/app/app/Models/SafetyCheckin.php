<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Báo tình trạng an toàn của du khách (F8) và phản hồi của cơ quan. */
class SafetyCheckin extends Model
{
    use HasFactory;

    public const STATUS_SAFE = 'safe';
    public const STATUS_NEED_HELP = 'need_help';
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUSES = [self::STATUS_SAFE, self::STATUS_NEED_HELP, self::STATUS_UNKNOWN];
    public const SOURCES = ['app', 'share_link', 'agency'];
    public const RESPONSE_STATUSES = ['open', 'in_progress', 'closed'];

    protected $fillable = [
        'trip_id', 'user_id', 'alert_id', 'status', 'lat', 'lng', 'accuracy_m', 'message', 'locale', 'source', 'checked_at',
        'responded_by', 'responded_at', 'response_note', 'response_status',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'accuracy_m' => 'integer',
            'checked_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'checkin_id');
    }
}
