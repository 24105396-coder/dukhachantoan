<?php

namespace App\Exceptions;

use RuntimeException;

/** Lỗi nghiệp vụ đặt lịch (thiếu chỗ, khung giờ đóng, chuyển trạng thái sai). */
class BookingException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'quantity', public readonly ?int $available = null)
    {
        parent::__construct($message);
    }

    public static function notEnoughSeats(int $available): self
    {
        return new self(__('messages.booking.only_left', ['count' => $available]), 'quantity', $available);
    }

    public static function slotUnavailable(): self
    {
        return new self(__('messages.booking.slot_unavailable'), 'slot');
    }

    public static function tooManyActive(int $max): self
    {
        return new self(__('messages.booking.too_many_active', ['max' => $max]), 'quantity');
    }

    public static function dailySeatLimit(int $max, int $left): self
    {
        return new self(__('messages.booking.daily_seat_limit', ['max' => $max, 'left' => $left]), 'quantity', $left);
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('messages.booking.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function cannotCancel(): self
    {
        return new self(__('messages.booking.cannot_cancel'), 'status');
    }

    public static function cannotPay(): self
    {
        return new self(__('messages.booking.cannot_pay'), 'status');
    }
}
