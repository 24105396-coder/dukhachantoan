<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    /** Route quản trị {booking}: chấp nhận id số hoặc mã BK-xxxx; route công khai dùng {booking:code}. */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field === null && is_string($value) && ! ctype_digit($value)) {
            return $this->newQuery()->where('code', $value)->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public const HELD = 'held';
    public const PENDING_PAYMENT = 'pending_payment';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const REFUNDED = 'refunded';

    /** Máy trạng thái: trạng thái hiện tại => các trạng thái được phép chuyển tới. */
    public const TRANSITIONS = [
        self::HELD => [self::PENDING_PAYMENT, self::PAID, self::CANCELLED, self::EXPIRED],
        self::PENDING_PAYMENT => [self::PAID, self::CANCELLED, self::EXPIRED, self::HELD],
        self::PAID => [self::REFUNDED, self::CANCELLED],
        self::CANCELLED => [],
        self::EXPIRED => [],
        self::REFUNDED => [],
    ];

    protected $fillable = [
        'code', 'slot_id', 'user_id', 'quantity', 'unit_price_vnd', 'total_vnd', 'status', 'hold_expires_at',
        'contact_name', 'contact_phone', 'contact_email', 'note', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = ['hold_expires_at' => 'datetime', 'cancelled_at' => 'datetime', 'quantity' => 'integer', 'total_vnd' => 'integer'];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TourSlot::class, 'slot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Chỗ đang chiếm giữ sức chứa (held, chờ thanh toán, đã thanh toán). */
    public function occupiesCapacity(): bool
    {
        return in_array($this->status, [self::HELD, self::PENDING_PAYMENT, self::PAID], true);
    }

    public function isHoldExpired(): bool
    {
        return $this->status === self::HELD && $this->hold_expires_at && $this->hold_expires_at->isPast();
    }
}
