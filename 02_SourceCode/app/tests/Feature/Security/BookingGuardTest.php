<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\TourRoute;
use App\Models\TourSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** M5: chỉ đặt được khung giờ mở, tương lai, thuộc tuyến đã công bố; M6: hạn mức giữ chỗ mỗi tài khoản. */
class BookingGuardTest extends TestCase
{
    use RefreshDatabase;

    private function payload(int $quantity = 1): array
    {
        return ['quantity' => $quantity, 'contact_name' => 'Khách', 'contact_phone' => '0901234567', 'contact_email' => 'k@example.com'];
    }

    private function hold(User $user, TourSlot $slot, int $quantity = 1)
    {
        return $this->actingAs($user)->from('/vi/tuyen-tham-quan')->post("/vi/dat-lich/{$slot->id}", $this->payload($quantity));
    }

    // ---------- M5 ----------

    public function test_cannot_hold_slot_of_draft_route(): void
    {
        $slot = TourSlot::factory()->for(TourRoute::factory()->draft(), 'route')->create();

        $this->hold($this->member(), $slot)->assertSessionHasErrors(['quantity' => __('messages.booking.slot_unavailable')]);
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, $slot->fresh()->booked_count);
    }

    public function test_cannot_hold_slot_of_soft_deleted_route(): void
    {
        $route = TourRoute::factory()->create();
        $slot = TourSlot::factory()->for($route, 'route')->create();
        $route->delete();

        $this->hold($this->member(), $slot)->assertSessionHasErrors('quantity');
        $this->assertSame(0, Booking::count());
    }

    public function test_cannot_hold_closed_or_past_slot(): void
    {
        $closed = TourSlot::factory()->create(['status' => 'closed']);
        $past = TourSlot::factory()->startingAt(now()->subHour())->create();
        $user = $this->member();

        $this->hold($user, $closed)->assertSessionHasErrors('quantity');
        $this->hold($user, $past)->assertSessionHasErrors('quantity');
        $this->assertSame(0, Booking::count());
    }

    public function test_published_future_open_slot_can_be_held(): void
    {
        $slot = TourSlot::factory()->create();

        $this->hold($this->member(), $slot, 2)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, $slot->fresh()->booked_count);
    }

    // ---------- M6 ----------

    public function test_user_cannot_have_more_than_three_unpaid_bookings(): void
    {
        $user = $this->member();
        $slots = TourSlot::factory()->count(4)->create();

        foreach ($slots->take(3) as $slot) {
            $this->hold($user, $slot)->assertSessionHasNoErrors();
        }
        $this->hold($user, $slots[3])->assertSessionHasErrors(['quantity' => __('messages.booking.too_many_active', ['max' => 3])]);

        $this->assertSame(3, Booking::where('user_id', $user->id)->count());
        $this->assertSame(0, $slots[3]->fresh()->booked_count);
        // Người khác không bị ảnh hưởng
        $this->hold($this->member(), $slots[3])->assertSessionHasNoErrors();
    }

    public function test_expired_or_paid_bookings_do_not_count_as_active(): void
    {
        $user = $this->member();
        Booking::factory()->count(2)->for($user)->expiredHold()->create(['quantity' => 1]);
        Booking::factory()->count(2)->for($user)->paid()->create(['quantity' => 1]);
        Booking::factory()->for($user)->create(['quantity' => 1]); // 1 held còn hạn

        $this->hold($user, TourSlot::factory()->create())->assertSessionHasNoErrors();
    }

    public function test_user_cannot_book_more_than_ten_seats_per_day(): void
    {
        $user = $this->member();
        Booking::factory()->for($user)->paid()->create(['quantity' => 8]);
        $slot = TourSlot::factory()->create();

        $this->hold($user, $slot, 3)->assertSessionHasErrors(['quantity' => __('messages.booking.daily_seat_limit', ['max' => 10, 'left' => 2])]);
        $this->assertSame(0, $slot->fresh()->booked_count);

        $this->hold($user, $slot, 2)->assertSessionHasNoErrors();
        $this->assertSame(2, $slot->fresh()->booked_count);
    }

    public function test_cancelled_bookings_and_previous_days_do_not_count_toward_daily_limit(): void
    {
        $user = $this->member();
        Booking::factory()->for($user)->create(['quantity' => 10, 'status' => Booking::CANCELLED]);
        $old = Booking::factory()->for($user)->paid()->create(['quantity' => 10]);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->hold($user, TourSlot::factory()->create(), 5)->assertSessionHasNoErrors();
    }
}
