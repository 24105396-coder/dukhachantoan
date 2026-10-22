<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TourRoute;
use App\Models\TourSlot;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\BookingService;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\SandboxGateway;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/** H3 (khóa HMAC, ẩn txn_ref với contributor), M4 (IPN trong transaction, tiền đến muộn), M7 (route sandbox theo cấu hình). */
class PaymentHardeningTest extends TestCase
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
            ->pendingPayment()->create(['quantity' => 2, 'unit_price_vnd' => 100000, 'total_vnd' => 200000]);
    }

    private function payment(): Payment
    {
        return $this->booking->payments()->create([
            'gateway' => 'sandbox', 'txn_ref' => 'SB-TEST-'.uniqid(), 'amount_vnd' => 200000, 'currency' => 'VND', 'status' => 'initiated',
        ]);
    }

    private function sendCallback(Payment $payment, string $outcome = 'success'): Payment
    {
        $service = app(PaymentService::class);
        $signed = $service->simulate($payment, $outcome);

        return $service->handleCallback($signed['payload'], $signed['signature']);
    }

    // ---------- H3 ----------

    public function test_production_refuses_empty_or_public_default_secret(): void
    {
        $provider = new AppServiceProvider($this->app);
        $this->app['env'] = 'production';

        foreach (['', 'sandbox-secret-change-me'] as $secret) {
            config(['disanso.payment.sandbox_secret' => $secret]);
            try {
                $provider->ensurePaymentSecret();
                $this->fail("Secret '$secret' must be rejected in production");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('PAYMENT_SANDBOX_SECRET', $e->getMessage());
            }
        }

        config(['disanso.payment.sandbox_secret' => bin2hex(random_bytes(32))]);
        $provider->ensurePaymentSecret();
        $this->app['env'] = 'testing';
        $this->addToAssertionCount(1);
    }

    public function test_config_has_no_hardcoded_default_secret(): void
    {
        $this->assertStringNotContainsString('sandbox-secret-change-me', (string) file_get_contents(config_path('disanso.php')));
    }

    public function test_signature_with_public_default_key_is_rejected(): void
    {
        $payment = $this->payment();
        $payload = app(SandboxGateway::class)->buildPayload($payment, 'success');
        ksort($payload);
        $forged = hash_hmac('sha256', http_build_query($payload, '', '&', PHP_QUERY_RFC3986), 'sandbox-secret-change-me');

        $this->postJson('/api/v1/payments/sandbox/ipn', $payload + ['signature' => $forged])->assertStatus(400);
        $this->assertSame('initiated', $payment->fresh()->status);
    }

    public function test_gateway_fails_closed_when_secret_missing(): void
    {
        $payment = $this->payment();
        config(['disanso.payment.sandbox_secret' => '']);
        $gateway = new SandboxGateway();
        $payload = $gateway->buildPayload($payment, 'success');

        $this->assertFalse($gateway->verify($payload, $gateway->sign($payload)));
    }

    public function test_contributor_does_not_see_payment_txn_ref_but_staff_does(): void
    {
        $contributor = $this->contributor();
        $route = TourRoute::factory()->ownedBy($contributor)->create();
        $slot = TourSlot::factory()->for($route, 'route')->create();
        $booking = Booking::factory()->for($slot, 'slot')->pendingPayment()->create();
        $booking->payments()->create(['gateway' => 'sandbox', 'txn_ref' => 'SB-SECRET-REF', 'amount_vnd' => 200000, 'currency' => 'VND', 'status' => 'initiated']);

        $response = $this->actingAs($contributor)->get('/vi/quan-tri/dat-lich')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('bookings.data', 1)->where('bookings.data.0.payments', []));
        $this->assertStringNotContainsString('SB-SECRET-REF', $response->getContent());

        $this->actingAs($this->admin())->get('/vi/quan-tri/dat-lich')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('bookings.data.0.payments.0.txn_ref', 'SB-SECRET-REF'));
    }

    // ---------- M4 ----------

    public function test_success_after_expire_job_records_refund_and_keeps_booking_expired(): void
    {
        $payment = $this->payment();
        $this->booking->forceFill(['hold_expires_at' => now()->subMinute()])->save();
        app(BookingService::class)->expireStaleHolds();
        $this->assertSame('expired', $this->booking->fresh()->status);
        $this->assertSame(0, $this->slot->fresh()->booked_count);
        // Job hết hạn đã hủy giao dịch đang chờ; IPN thành công đến muộn cho một giao dịch mới khởi tạo
        $late = $this->payment();

        $result = $this->sendCallback($late);

        $this->assertSame('refunded', $result->status);
        $this->assertNotNull($result->paid_at);
        $this->assertNotNull($result->refunded_at);
        $this->assertSame('expired', $this->booking->fresh()->status);
        $this->assertSame(0, $this->slot->fresh()->booked_count, 'Không chiếm lại chỗ đã trả');
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.late_refunded']);
    }

    public function test_success_for_cancelled_booking_is_refunded_and_booking_unchanged(): void
    {
        $payment = $this->payment();
        $this->booking->forceFill(['status' => Booking::CANCELLED])->save();

        $this->assertSame('refunded', $this->sendCallback($payment)->status);
        $this->assertSame('cancelled', $this->booking->fresh()->status);
    }

    public function test_second_callback_for_same_payment_is_idempotent(): void
    {
        $payment = $this->payment();

        $this->assertSame('succeeded', $this->sendCallback($payment, 'success')->status);
        // Callback "cancel" đến sau (nút sandbox + IPN): không đổi kết quả đã chốt
        $this->assertSame('succeeded', $this->sendCallback($payment->fresh(), 'cancel')->status);
        $this->assertSame('paid', $this->booking->fresh()->status);
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'payment.succeeded')->count());
    }

    public function test_unknown_txn_ref_is_rejected_with_400(): void
    {
        $gateway = app(SandboxGateway::class);
        $payload = ['txn_ref' => 'SB-NOPE', 'amount' => 1, 'outcome' => 'success'];

        $this->postJson('/api/v1/payments/sandbox/ipn', $payload + ['signature' => $gateway->sign($payload)])
            ->assertStatus(400)->assertJson(['message' => 'Unknown transaction']);
    }

    // ---------- M7 ----------

    public function test_sandbox_routes_absent_when_gateway_is_not_sandbox(): void
    {
        $payment = $this->payment();
        config(['disanso.payment.gateway' => 'vnpay']);
        $this->app->offsetUnset(PaymentGateway::class);
        (new AppServiceProvider($this->app))->register();
        $this->reloadRoutes();

        $this->assertFalse($this->app->bound(PaymentGateway::class));
        $this->actingAs($this->user)->get("/vi/cong-thanh-toan/{$payment->txn_ref}")->assertNotFound();
        $this->actingAs($this->user)->post("/vi/cong-thanh-toan/{$payment->txn_ref}/ket-qua", ['outcome' => 'success'])->assertNotFound();
        $this->postJson('/api/v1/payments/sandbox/ipn', ['txn_ref' => $payment->txn_ref, 'amount' => 200000, 'outcome' => 'success'])->assertNotFound();
        $this->actingAs($this->user)->post("/vi/dat-lich/{$this->booking->code}/thanh-toan")
            ->assertSessionHas('error', __('messages.payment.unavailable'));
        $this->assertSame('initiated', $payment->fresh()->status);
        $this->assertSame(1, Payment::count());
    }

    public function test_sandbox_routes_present_with_sandbox_gateway(): void
    {
        $payment = $this->payment();

        $this->actingAs($this->user)->get("/vi/cong-thanh-toan/{$payment->txn_ref}")->assertOk();
    }

    /** Nạp lại routes/web.php + routes/api.php như bootstrap/app.php để áp cấu hình cổng mới. */
    private function reloadRoutes(): void
    {
        $router = $this->app['router'];
        $router->setRoutes(new RouteCollection());
        $router->middleware('web')->group(base_path('routes/web.php'));
        $router->middleware('api')->prefix('api')->group(base_path('routes/api.php'));
        $router->getRoutes()->refreshNameLookups();
        $this->app['url']->setRoutes($router->getRoutes());
    }
}
