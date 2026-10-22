<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\TourSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Lưu ý: factory không cập nhật tour_slots.booked_count; test tự điều chỉnh nếu cần.
 *
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'code' => 'BK-'.Str::upper(Str::random(8)),
            'slot_id' => TourSlot::factory(),
            'user_id' => User::factory(),
            'quantity' => 1,
            'unit_price_vnd' => 200000,
            'total_vnd' => 200000,
            'status' => Booking::HELD,
            'hold_expires_at' => now()->addMinutes(10),
            'contact_name' => fake()->name(),
            'contact_phone' => '0901234567',
            'contact_email' => fake()->safeEmail(),
            'note' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => Booking::PAID, 'hold_expires_at' => null]);
    }

    public function pendingPayment(): static
    {
        return $this->state(['status' => Booking::PENDING_PAYMENT, 'hold_expires_at' => now()->addMinutes(10)]);
    }

    public function expiredHold(): static
    {
        return $this->state(['status' => Booking::HELD, 'hold_expires_at' => now()->subMinutes(5)]);
    }
}
