<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TourSlot;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expire_stale_holds_releases_seats(): void
    {
        $slot = TourSlot::factory()->capacity(10, 5)->create();
        $stale = Booking::factory()->for($slot, 'slot')->expiredHold()->create(['quantity' => 3]);
        $fresh = Booking::factory()->for($slot, 'slot')->create(['quantity' => 2]);

        $count = app(BookingService::class)->expireStaleHolds();

        $this->assertSame(1, $count);
        $this->assertSame('expired', $stale->fresh()->status);
        $this->assertSame('held', $fresh->fresh()->status);
        $this->assertSame(2, $slot->fresh()->booked_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.expired', 'subject_id' => $stale->id]);
    }

    public function test_artisan_command_expires_pending_payment_and_cancels_open_payment(): void
    {
        $slot = TourSlot::factory()->capacity(4, 2)->create();
        $booking = Booking::factory()->for($slot, 'slot')->create([
            'status' => 'pending_payment', 'quantity' => 2, 'hold_expires_at' => now()->subMinute(),
        ]);
        $payment = Payment::create(['booking_id' => $booking->id, 'gateway' => 'sandbox', 'txn_ref' => 'SB-TEST-1', 'amount_vnd' => 400000, 'status' => 'initiated']);

        $this->artisan('app:expire-holds')->expectsOutputToContain('Released 1')->assertSuccessful();

        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame(0, $slot->fresh()->booked_count);
    }

    public function test_new_hold_reclaims_seats_of_expired_hold_on_same_slot(): void
    {
        $slot = TourSlot::factory()->capacity(1, 1)->create();
        $stale = Booking::factory()->for($slot, 'slot')->expiredHold()->create();

        $this->actingAs($this->member())->post("/vi/dat-lich/{$slot->id}", [
            'quantity' => 1, 'contact_name' => 'Khách mới', 'contact_phone' => '0901234567', 'contact_email' => 'moi@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertSame('expired', $stale->fresh()->status);
        $this->assertSame(1, $slot->fresh()->booked_count);
        $this->assertSame(1, Booking::where('status', 'held')->count());
    }

    public function test_expired_hold_cannot_be_paid(): void
    {
        $user = $this->member();
        $booking = Booking::factory()->for($user)->expiredHold()->create();

        $this->actingAs($user)->post("/vi/dat-lich/{$booking->code}/thanh-toan")->assertSessionHas('error');
        $this->assertSame(0, Payment::count());
    }

    public function test_booking_page_shows_countdown_and_flags(): void
    {
        $user = $this->member();
        $booking = Booking::factory()->for($user)->create();

        $this->actingAs($user)->get("/vi/dat-lich/{$booking->code}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Bookings/Show')
                ->where('booking.code', $booking->code)
                ->where('booking.can_pay', true)
                ->where('booking.can_cancel', true)
                ->where('sandboxGateway', true)
                ->where('booking.seconds_left', fn ($s) => $s > 500 && $s <= 600));
    }

    public function test_member_can_cancel_held_booking_and_seat_is_returned(): void
    {
        $user = $this->member();
        $slot = TourSlot::factory()->capacity(5, 2)->create();
        $booking = Booking::factory()->for($user)->for($slot, 'slot')->create(['quantity' => 2]);

        $this->actingAs($user)->post("/vi/dat-lich/{$booking->code}/huy")->assertSessionHas('success');

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(0, $slot->fresh()->booked_count);
    }

    public function test_paid_booking_within_24h_cannot_be_cancelled(): void
    {
        $user = $this->member();
        $slot = TourSlot::factory()->startingAt(now()->addHours(10))->capacity(5, 1)->create();
        $booking = Booking::factory()->for($user)->for($slot, 'slot')->paid()->create();

        $this->actingAs($user)->post("/vi/dat-lich/{$booking->code}/huy")->assertSessionHas('error');
        $this->assertSame('paid', $booking->fresh()->status);
    }
}
