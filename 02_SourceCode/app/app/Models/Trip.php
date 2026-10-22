<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Hành trình du khách (F1). URL dùng mã TR-xxxxxxxx ngẫu nhiên; trang chia sẻ dùng share_token 40 ký tự.
 */
class Trip extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    public const CODE_PREFIX = 'TR-';
    public const CODE_LENGTH = 8;
    public const SHARE_TOKEN_LENGTH = 40;

    protected $fillable = [
        'code', 'user_id', 'partner_id', 'title', 'start_date', 'end_date', 'group_size', 'nationality', 'preferred_locale',
        'contact_name', 'contact_phone', 'contact_email', 'status', 'share_token', 'risk_level_cached', 'last_alert_at',
        'cancelled_at',
    ];

    protected $hidden = ['share_token'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'group_size' => 'integer',
            'risk_level_cached' => 'integer',
            'last_alert_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Trip $trip) {
            $trip->code ??= self::generateCode();
            $trip->share_token ??= self::generateShareToken();
        });
    }

    /** TR- + 8 ký tự in hoa/số ngẫu nhiên, thử lại nếu trùng. */
    public static function generateCode(): string
    {
        do {
            $code = self::CODE_PREFIX.Str::upper(Str::random(self::CODE_LENGTH));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    public static function generateShareToken(): string
    {
        do {
            $token = Str::random(self::SHARE_TOKEN_LENGTH);
        } while (self::query()->where('share_token', $token)->exists());

        return $token;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->user();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(TripLeg::class)->orderBy('leg_order');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TripMember::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(AlertMatch::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(SafetyCheckin::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class);
    }

    /** Hành trình còn hiệu lực (chưa hủy/hoàn tất, chưa kết thúc). */
    public function scopeOngoing($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)->whereDate('end_date', '>=', now()->toDateString());
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ACTIVE], true);
    }
}
