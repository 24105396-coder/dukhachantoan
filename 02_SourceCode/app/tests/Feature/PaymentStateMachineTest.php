<?php

namespace Tests\Feature;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TourSlot;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Payment\SandboxGateway;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TourSlot $slot;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->member();
        $this->slot = TourSlot::factory()->startingAt(now()->addDays(5))->capacity(10, 2)->create();
        $this->booking = Booking::factory()->for($this->user)->for($this->slot, 'slot')
            ->create(['quantity' => 2, 'unit_price_vnd' => 200000, 'total_vnd' => 400000]);
    }

    private function startPayment(): Payment
    {
        $this->actingAs($this->user)->post("/vi/dat-lich/{$this->booking->code}/thanh-toan")->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->assertSame('pending_payment', $this->booking->fresh()->status);
        $this->assertMatchesRegularExpression('/^SB-\d{12}-[A-Z0-9]{6}$/', $payment->txn_ref);

        return $payment;
    }

    private function choose(Payment $payment, string $outcome)
    {
        return $this->actingAs($this->user)->post("/vi/cong-thanh-toan/{$payment->txn_ref}/ket-qua", ['outcome' => $outcome]);
    }

    public function test_sandbox_page_renders_payment_props(): void
    {
        $payment = $this->startPayment();

        $this->actingAs($this->user)->get("/vi/cong-thanh-toan/{$payment->txn_ref}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Payments/Sandbox')
                ->where('payment.txn_ref', $payment->txn_ref)
                ->where('payment.amount_vnd', 400000)
                ->where('payment.booking_code', $this->booking->code));
    }

    public function test_success_outcome_marks_booking_paid(): void
    {
        $payment = $this->startPayment();

        $this->choose($payment, 'success')->assertRedirect(localized_route('bookings.show', ['locale' => 'vi', 'booking' => $this->booking->code]))
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertSame('succeeded', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(64, strlen($payment->signature));
        $this->assertSame('paid', $this->booking->fresh()->status);
        $this->assertNull($this->booking->fresh()->hold_expires_at);
        $this->assertSame(2, $this->slot->fresh()->booked_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.succeeded']);
    }

    public function test_fail_outcome_returns_booking_to_held(): void
    {
        $payment = $this->startPayment();

        $this->choose($payment, 'fail')->assertSessionHas('error');

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('held', $this->booking->fresh()->status);
        $this->assertSame(2, $this->slot->fresh()->booked_count);
    }

    public function test_cancel_outcome_marks_payment_cancelled_and_allows_retry(): void
    {
        $payment = $this->startPayment();

        $this->choose($payment, 'cancel')->assertSessionHas('error');
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame('held', $this->booking->fresh()->status);

        $this->actingAs($this->user)->post("/vi/dat-lich/{$this->booking->code}/thanh-toan")->assertRedirect();
        $this->assertSame(2, Payment::count());
    }

    public function test_ipn_with_invalid_signature_returns_400_and_changes_nothing(): void
    {
        $payment = $this->startPayment();
        $payload = app(SandboxGateway::class)->buildPayload($payment, 'success');

        $this->postJson('/api/v1/payments/sandbox/ipn', $payload + ['signature' => str_repeat('a', 64)])->assertStatus(400);

        $this->assertSame('initiated', $payment->fresh()->status);
        $this->assertSame('pending_payment', $this->booking->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.invalid_signature']);
    }

    public function test_ipn_with_tampered_amount_is_rejected(): void
    {
        $payment = $this->startPayment();
        $gateway = app(SandboxGateway::class);
        $payload = $gateway->buildPayload($payment, 'success');
        $signature = $gateway->sign($payload);
        $payload['amount'] = 1000;

        $this->postJson('/api/v1/payments/sandbox/ipn', $payload + ['signature' => $signature])->assertStatus(400);
        $this->assertSame('initiated', $payment->fresh()->status);
    }

    public function test_ipn_with_valid_signature_is_processed_once(): void
    {
        $payment = $this->startPayment();
        $gateway = app(SandboxGateway::class);
        $payload = $gateway->buildPayload($payment, 'success');
        $body = $payload + ['signature' => $gateway->sign($payload)];

        $this->postJson('/api/v1/payments/sandbox/ipn', $body)->assertOk()->assertJson(['status' => 'succeeded', 'booking_status' => 'paid']);
        $this->postJson('/api/v1/payments/sandbox/ipn', $body)->assertOk()->assertJson(['status' => 'succeeded']);
        $this->assertSame(1, Payment::where('status', 'succeeded')->count());
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $service = app(BookingService::class);
        $expired = Booking::factory()->create(['status' => 'expired']);

        $this->expectException(BookingException::class);
        $service->transition($expired, Booking::PAID);
    }

    public function test_admin_cannot_move_paid_booking_back_to_held(): void
    {
        $paid = Booking::factory()->for($this->slot, 'slot')->paid()->create();

        $this->actingAs($this->admin())->from('/vi/quan-tri/dat-lich')
            ->patch("/vi/quan-tri/dat-lich/{$paid->code}/trang-thai", ['status' => 'held'])
            ->assertSessionHasErrors('status');
        $this->assertSame('paid', $paid->fresh()->status);
    }

    public function test_admin_refund_releases_seats_and_refunds_payment(): void
    {
        $payment = $this->startPayment();
        $this->choose($payment, 'success');

        $this->actingAs($this->admin())->patch("/vi/quan-tri/dat-lich/{$this->booking->code}/trang-thai", ['status' => 'refunded', 'reason' => 'Khách đổi lịch'])
            ->assertSessionHas('success');

        $this->assertSame('refunded', $this->booking->fresh()->status);
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->refunded_at);
        $this->assertSame(0, $this->slot->fresh()->booked_count);
    }

    public function test_member_cancelling_paid_booking_more_than_24h_ahead_gets_refund(): void
    {
        $payment = $this->startPayment();
        $this->choose($payment, 'success');

        $this->actingAs($this->user)->post("/vi/dat-lich/{$this->booking->code}/huy")->assertSessionHas('success');
        $this->assertSame('refunded', $this->booking->fresh()->status);
    }

    public function test_success_after_hold_expiry_is_not_accepted(): void
    {
        $payment = $this->startPayment();
        $this->booking->fresh()->forceFill(['hold_expires_at' => now()->subMinute()])->save();

        app(PaymentService::class)->handleCallback(...array_values(app(PaymentService::class)->simulate($payment, 'success')));

        // M4: tiền đến muộn → ghi nhận rồi hoàn ngay (sandbox), booking không thành paid
        $payment->refresh();
        $this->assertSame('refunded', $payment->status);
        $this->assertNotNull($payment->refunded_at);
        $this->assertSame('late_payment_auto_refund', $payment->gateway_payload['note']);
        $this->assertNotSame('paid', $this->booking->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.late_refunded']);
    }
}
