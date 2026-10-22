<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Thông báo nghiệp vụ phân cấp 4 mức (F4), gửi qua hàng đợi có thử lại lũy tiến.
 */
class Notification extends Model
{
    use HasFactory;

    public const CHANNEL_INAPP = 'inapp';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNELS = [self::CHANNEL_INAPP, self::CHANNEL_EMAIL, self::CHANNEL_SMS];

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_READ = 'read';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [self::STATUS_QUEUED, self::STATUS_SENDING, self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_READ, self::STATUS_CANCELLED];

    /** Số phút chờ trước lần thử thứ n+1 sau n lần thất bại (chỉ số = attempts - 1). */
    public const BACKOFF_MINUTES = [1, 5, 15, 60, 240];
    public const DEFAULT_MAX_ATTEMPTS = 5;
    /** Mức 4 chưa đọc sau số phút này → nhắc lại. */
    public const EMERGENCY_REMIND_AFTER_MINUTES = 30;

    protected $fillable = [
        'user_id', 'trip_id', 'alert_id', 'match_id', 'checkin_id', 'channel', 'level', 'locale', 'title', 'body',
        'action_url', 'status', 'attempts', 'max_attempts', 'next_attempt_at', 'sent_at', 'read_at', 'last_error',
        'provider_ref', 'payload',
    ];

    protected $attributes = ['status' => self::STATUS_QUEUED, 'attempts' => 0, 'max_attempts' => self::DEFAULT_MAX_ATTEMPTS];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(AlertMatch::class, 'match_id');
    }

    public function checkin(): BelongsTo
    {
        return $this->belongsTo(SafetyCheckin::class, 'checkin_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class)->orderBy('attempt_no');
    }

    /** Phút chờ sau lần thất bại thứ $attempts (1-based); vượt bảng thì dùng giá trị cuối. */
    public static function backoffMinutes(int $attempts): int
    {
        $index = max(0, min($attempts, count(self::BACKOFF_MINUTES)) - 1);

        return self::BACKOFF_MINUTES[$index];
    }

    /** Đến hạn gửi: đang xếp hàng và next_attempt_at rỗng hoặc đã qua. */
    public function scopeDue($query)
    {
        return $query->where('status', self::STATUS_QUEUED)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()));
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
