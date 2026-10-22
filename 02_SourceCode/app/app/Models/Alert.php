<?php

namespace App\Models;

use App\Models\Concerns\HasGeometry;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Cảnh báo rủi ro (F2) 4 mức, máy trạng thái TRANSITIONS; luôn ghi nguồn và thời điểm cập nhật.
 */
class Alert extends Model
{
    use HasFactory, HasGeometry, HasMedia, Translatable;

    public const SEVERITY_INFO = 1;
    public const SEVERITY_ADVISORY = 2;
    public const SEVERITY_WARNING = 3;
    public const SEVERITY_EMERGENCY = 4;
    public const SEVERITIES = [1 => 'info', 2 => 'advisory', 3 => 'warning', 4 => 'emergency'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_UPDATED, self::STATUS_EXPIRED, self::STATUS_CANCELLED];
    /** Trạng thái đang hiệu lực (hiển thị công khai, đối sánh). */
    public const LIVE_STATUSES = [self::STATUS_ACTIVE, self::STATUS_UPDATED];

    /** Chuyển trạng thái hợp lệ: draft→active, active→updated|expired|cancelled, updated→active|expired|cancelled. */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_ACTIVE],
        self::STATUS_ACTIVE => [self::STATUS_UPDATED, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
        self::STATUS_UPDATED => [self::STATUS_ACTIVE, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
        self::STATUS_EXPIRED => [],
        self::STATUS_CANCELLED => [],
    ];

    public const CODE_PREFIX = 'AL-';
    public const CODE_LENGTH = 8;

    protected string $translationModel = AlertTranslation::class;
    protected string $translationForeignKey = 'alert_id';

    protected $fillable = [
        'code', 'source_id', 'hazard_type_id', 'severity', 'status', 'geometry', 'starts_at', 'ends_at', 'issued_at',
        'updated_at_source', 'source_name', 'source_url', 'raw_payload', 'is_simulated', 'scenario_id', 'created_by',
        'published_at', 'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => 'integer',
            'geometry' => 'array',
            'raw_payload' => 'array',
            'is_simulated' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'issued_at' => 'datetime',
            'updated_at_source' => 'datetime',
            'published_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Alert $alert) {
            $alert->code ??= self::generateCode();
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = self::CODE_PREFIX.Str::upper(Str::random(self::CODE_LENGTH));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(AlertSource::class, 'source_id');
    }

    public function hazardType(): BelongsTo
    {
        return $this->belongsTo(HazardType::class);
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'alert_zones')->using(AlertZone::class)->withTimestamps();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(AlertMatch::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(SafetyCheckin::class);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(SimulationScenario::class, 'scenario_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getHeadlineAttribute(): string
    {
        return (string) ($this->translated('headline') ?? $this->code);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE_STATUSES, true) && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /** Đang hiệu lực: trạng thái active/updated và chưa hết hạn. */
    public function scopeLive($query)
    {
        return $query->whereIn('status', self::LIVE_STATUSES)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public static function severityLabel(int $severity): string
    {
        return __('enums.severity.'.(self::SEVERITIES[$severity] ?? 'info'));
    }

    /** Cảnh báo có vùng thuộc tỉnh. */
    public function scopeInProvince($query, int $provinceId)
    {
        return $query->whereHas('zones', fn ($z) => $z->where('province_id', $provinceId));
    }

    /** Sắp xếp: mức cao trước, mới cập nhật trước. */
    public function scopeMostSevere($query)
    {
        return $query->orderByDesc('severity')->orderByDesc('updated_at_source')->orderByDesc('id');
    }
}
