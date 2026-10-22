<?php

namespace Tests\Feature;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\TourSlot;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Giữ chỗ tuần tự: chỗ cuối cùng chỉ được cấp một lần. Bản song song thật ở BookingParallelHoldTest. */
class BookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function payload(int $quantity = 1): array
    {
        return ['quantity' => $quantity, 'contact_name' => 'Khách', 'contact_phone' => '0901234567', 'contact_email' => 'k@example.com'];
    }

    public function test_second_hold_on_last_seat_fails_with_quantity_error(): void
    {
        $slot = TourSlot::factory()->capacity(5, 4)->create();
        $first = $this->member();
        $second = $this->member();

        $this->actingAs($first)->post("/vi/dat-lich/{$slot->id}", $this->payload())->assertRedirect();
        $this->actingAs($second)->from('/vi/tuyen-tham-quan')->post("/vi/dat-lich/{$slot->id}", $this->payload())
            ->assertSessionHasErrors(['quantity' => 'Chỉ còn 0 chỗ.']);

        $this->assertSame(5, $slot->fresh()->booked_count);
        $this->assertSame(1, Booking::where('slot_id', $slot->id)->count());
    }

    public function test_hold_fails_when_quantity_exceeds_available_seats(): void
    {
        $slot = TourSlot::factory()->capacity(10, 7)->create();

        $this->actingAs($this->member())->post("/vi/dat-lich/{$slot->id}", $this->payload(4))
            ->assertSessionHasErrors(['quantity' => 'Chỉ còn 3 chỗ.']);
        $this->assertSame(7, $slot->fresh()->booked_count);
    }

    public function test_successful_hold_creates_booking_with_random_code_and_expiry(): void
    {
        $slot = TourSlot::factory()->create();
        $user = $this->member();

        $response = $this->actingAs($user)->post("/vi/dat-lich/{$slot->id}", $this->payload(3));

        $booking = Booking::firstOrFail();
        $response->assertRedirect(localized_route('bookings.show', ['locale' => 'vi', 'booking' => $booking->code]));
        $this->assertMatchesRegularExpression('/^BK-[A-Z0-9]{8}$/', $booking->code);
        $this->assertSame('held', $booking->status);
        $this->assertSame(600000, $booking->total_vnd);
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $booking->hold_expires_at->timestamp, 5);
        $this->assertSame(3, $slot->fresh()->booked_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.held', 'subject_type' => 'booking', 'subject_id' => $booking->id]);
    }

    public function test_service_throws_when_slot_closed_or_started(): void
    {
        $service = app(BookingService::class);
        $closed = TourSlot::factory()->create(['status' => 'closed']);
        $past = TourSlot::factory()->startingAt(now()->subHour())->create();

        foreach ([$closed, $past] as $slot) {
            try {
                $service->hold($slot, $this->member(), $this->payload());
                $this->fail('Expected BookingException');
            } catch (BookingException $e) {
                $this->assertSame('slot', $e->field);
            }
        }
        $this->assertSame(0, Booking::count());
    }

    public function test_many_sequential_holds_never_exceed_capacity(): void
    {
        $slot = TourSlot::factory()->capacity(3)->create();
        $service = app(BookingService::class);
        $ok = 0;
        for ($i = 0; $i < 6; $i++) {
            try {
                $service->hold($slot, $this->member(), $this->payload());
                $ok++;
            } catch (BookingException) {
            }
        }

        $this->assertSame(3, $ok);
        $this->assertSame(3, $slot->fresh()->booked_count);
    }
}
