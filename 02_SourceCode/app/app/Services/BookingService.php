<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TourSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nghiệp vụ đặt lịch: giữ chỗ có khóa dòng, hết hạn, hủy, thanh toán, hoàn tiền.
 * `tour_slots.booked_count` chỉ thay đổi bên trong transaction đã khóa dòng khung giờ.
 */
class BookingService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function holdMinutes(): int
    {
        return (int) config('disanso.booking.hold_minutes', 10);
    }

    /**
     * Giữ chỗ: khóa dòng khung giờ (SELECT ... FOR UPDATE) rồi mới kiểm tra sức chứa,
     * nên hai yêu cầu đồng thời không thể cùng lấy chỗ cuối cùng.
     */
    public function hold(TourSlot $slot, User $user, array $data): Booking
    {
        $quantity = (int) $data['quantity'];

        $booking = DB::transaction(function () use ($slot, $user, $data, $quantity) {
            // Khóa dòng user trước (thứ tự user → slot) để hai yêu cầu song song của cùng người
            // không cùng vượt hạn mức; transition() chỉ khóa slot → booking nên không tạo vòng khóa.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            /** @var TourSlot $locked */
            $locked = TourSlot::query()->whereKey($slot->getKey())->lockForUpdate()->firstOrFail();

            $this->assertSlotBookable($locked);
            $this->assertUserQuota($user, $quantity);
            $this->releaseExpiredOnLockedSlot($locked);

            $available = $locked->capacity - $locked->booked_count;
            if ($available < $quantity) {
                throw BookingException::notEnoughSeats(max(0, $available));
            }

            $locked->booked_count += $quantity;
            $locked->save();

            $unitPrice = (int) ($locked->route()->withTrashed()->value('price_vnd') ?? 0);

            return Booking::create([
                'code' => $this->generateCode(),
                'slot_id' => $locked->id,
                'user_id' => $user->id,
                'quantity' => $quantity,
                'unit_price_vnd' => $unitPrice,
                'total_vnd' => $unitPrice * $quantity,
                'status' => Booking::HELD,
                'hold_expires_at' => now()->addMinutes($this->holdMinutes()),
                'contact_name' => $data['contact_name'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'],
                'note' => $data['note'] ?? null,
            ]);
        });

        $this->audit->log('booking.held', $booking, ['code' => $booking->code, 'quantity' => $quantity], $user);

        return $booking;
    }

    /** Khung giờ mở, chưa bắt đầu và thuộc tuyến đã công bố, chưa xóa mềm. */
    private function assertSlotBookable(TourSlot $slot): void
    {
        $routePublished = $slot->route()->published()->exists(); // route() mặc định loại bản ghi đã xóa mềm
        if ($slot->status !== 'open' || $slot->starts_at->isPast() || ! $routePublished) {
            throw BookingException::slotUnavailable();
        }
    }

    /**
     * Chống chiếm giữ tồn kho: tối đa N đặt lịch chưa thanh toán còn hạn cùng lúc
     * và tối đa M chỗ (held/pending/paid/expired) tạo trong ngày cho mỗi tài khoản.
     */
    private function assertUserQuota(User $user, int $quantity): void
    {
        $maxActive = (int) config('disanso.booking.max_active_per_user', 3);
        $active = Booking::query()->where('user_id', $user->id)
            ->whereIn('status', [Booking::HELD, Booking::PENDING_PAYMENT])
            ->where(fn ($q) => $q->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>=', now()))
            ->count();
        if ($active >= $maxActive) {
            throw BookingException::tooManyActive($maxActive);
        }

        $maxSeats = (int) config('disanso.booking.max_seats_per_day', 10);
        $seatsToday = (int) Booking::query()->where('user_id', $user->id)
            ->whereNotIn('status', [Booking::CANCELLED, Booking::REFUNDED])
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('quantity');
        if ($seatsToday + $quantity > $maxSeats) {
            throw BookingException::dailySeatLimit($maxSeats, max(0, $maxSeats - $seatsToday));
        }
    }

    /** Giải phóng mọi phiếu giữ chỗ quá hạn (lệnh app:expire-holds chạy mỗi phút). */
    public function expireStaleHolds(): int
    {
        $ids = Booking::query()
            ->whereIn('status', [Booking::HELD, Booking::PENDING_PAYMENT])
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now())
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            $count += $this->expireOne((int) $id) ? 1 : 0;
        }

        return $count;
    }

    public function canPay(Booking $booking): bool
    {
        if (! in_array($booking->status, [Booking::HELD, Booking::PENDING_PAYMENT], true)) {
            return false;
        }

        return ! $booking->hold_expires_at || $booking->hold_expires_at->isFuture();
    }

    /** Được hủy khi đang giữ/chờ thanh toán, hoặc đã thanh toán và còn hơn 24 giờ trước giờ khởi hành. */
    public function canCancel(Booking $booking): bool
    {
        $booking->loadMissing('slot');
        $startsAt = $booking->slot?->starts_at;
        if (! $startsAt || $startsAt->isPast()) {
            return false;
        }
        if (in_array($booking->status, [Booking::HELD, Booking::PENDING_PAYMENT], true)) {
            return true;
        }
        $hours = (int) config('disanso.booking.cancel_before_hours', 24);

        return $booking->status === Booking::PAID && now()->addHours($hours)->lt($startsAt);
    }

    public function cancel(Booking $booking, ?User $actor = null, ?string $reason = null): Booking
    {
        if (! $this->canCancel($booking)) {
            throw BookingException::cannotCancel();
        }

        return $booking->status === Booking::PAID
            ? $this->refund($booking, $actor, $reason)
            : $this->transition($booking, Booking::CANCELLED, $actor, $reason);
    }

    public function markPaid(Booking $booking, ?User $actor = null): Booking
    {
        return $this->transition($booking, Booking::PAID, $actor);
    }

    /** Hoàn tiền: booking paid → refunded, payment succeeded → refunded, trả chỗ. */
    public function refund(Booking $booking, ?User $actor = null, ?string $reason = null): Booking
    {
        return $this->transition($booking, Booking::REFUNDED, $actor, $reason);
    }

    /**
     * Điểm duy nhất thay đổi trạng thái booking, tuân thủ Booking::TRANSITIONS
     * và cập nhật sức chứa trong cùng transaction.
     */
    public function transition(Booking $booking, string $to, ?User $actor = null, ?string $reason = null): Booking
    {
        $from = $booking->status;

        $updated = DB::transaction(function () use ($booking, $to, $reason) {
            $slot = TourSlot::query()->whereKey($booking->slot_id)->lockForUpdate()->first();
            /** @var Booking $fresh */
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (! $fresh->canTransitionTo($to)) {
                throw BookingException::invalidTransition($fresh->status, $to);
            }
            $this->applyTransition($fresh, $slot, $to, $reason);

            return $fresh;
        });

        $this->audit->log('booking.'.$to, $updated, array_filter(['from' => $from, 'to' => $to, 'reason' => $reason]), $actor);
        $booking->setRawAttributes($updated->getAttributes(), true);

        return $updated;
    }

    private function applyTransition(Booking $booking, ?TourSlot $slot, string $to, ?string $reason): void
    {
        $occupiedBefore = $booking->occupiesCapacity();
        $booking->status = $to;

        if ($to === Booking::PAID) {
            $booking->hold_expires_at = null;
        }
        if ($to === Booking::PENDING_PAYMENT) {
            $minExpiry = now()->addMinutes($this->holdMinutes());
            $booking->hold_expires_at = $booking->hold_expires_at && $booking->hold_expires_at->gt($minExpiry)
                ? $booking->hold_expires_at : $minExpiry;
        }
        if (in_array($to, [Booking::CANCELLED, Booking::REFUNDED], true)) {
            $booking->cancelled_at = now();
            $booking->cancel_reason = $reason;
        }
        if ($to === Booking::REFUNDED) {
            $this->refundPayments($booking);
        }
        if (in_array($to, [Booking::CANCELLED, Booking::EXPIRED, Booking::REFUNDED], true)) {
            Payment::query()->where('booking_id', $booking->id)->where('status', 'initiated')->update(['status' => 'cancelled']);
        }
        $booking->save();

        if ($slot && $occupiedBefore && ! $booking->occupiesCapacity()) {
            $slot->booked_count = max(0, $slot->booked_count - $booking->quantity);
            $slot->save();
        }
    }

    private function refundPayments(Booking $booking): void
    {
        $paid = Payment::query()->where('booking_id', $booking->id)->where('status', 'succeeded')->get();
        foreach ($paid as $payment) {
            $payment->update(['status' => 'refunded', 'refunded_at' => now()]);
        }
    }

    private function expireOne(int $id): bool
    {
        $booking = Booking::find($id);
        if (! $booking || ! $booking->hold_expires_at || $booking->hold_expires_at->isFuture()) {
            return false;
        }
        try {
            $this->transition($booking, Booking::EXPIRED, null, 'hold_expired');

            return true;
        } catch (BookingException) {
            return false; // trạng thái đã đổi bởi tiến trình khác
        }
    }

    /** Dọn phiếu quá hạn của chính khung giờ đang khóa trước khi tính chỗ trống. */
    private function releaseExpiredOnLockedSlot(TourSlot $slot): void
    {
        $stale = Booking::query()
            ->where('slot_id', $slot->id)
            ->whereIn('status', [Booking::HELD, Booking::PENDING_PAYMENT])
            ->where('hold_expires_at', '<', now())
            ->lockForUpdate()
            ->get();

        foreach ($stale as $booking) {
            $this->applyTransition($booking, $slot, Booking::EXPIRED, 'hold_expired');
        }
    }

    public function generateCode(): string
    {
        do {
            $code = 'BK-'.Str::upper(Str::random(8));
        } while (Booking::where('code', $code)->exists());

        return $code;
    }
}
