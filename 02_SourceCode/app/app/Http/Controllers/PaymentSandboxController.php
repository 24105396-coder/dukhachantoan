<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentResultRequest;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trang cổng thanh toán mô phỏng. Nút kết quả tạo payload có chữ ký rồi đi qua
 * đúng đường xử lý callback (IPN) như cổng thật.
 */
class PaymentSandboxController extends Controller
{
    public function show(Request $request, Payment $payment): Response
    {
        $payment->load('booking.slot.route.translations');
        $this->authorize('pay', $payment->booking);

        return Inertia::render('Payments/Sandbox', [
            'payment' => [
                'txn_ref' => $payment->txn_ref,
                'amount_vnd' => (int) $payment->amount_vnd,
                'status' => $payment->status,
                'booking_code' => $payment->booking->code,
                'route_name' => $payment->booking->slot?->route?->name,
                'expires_at' => $payment->booking->hold_expires_at?->toIso8601String(),
                'booking_url' => localized_route('bookings.show', ['booking' => $payment->booking->code]),
            ],
        ]);
    }

    public function result(PaymentResultRequest $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $this->authorize('pay', $payment->booking);

        $simulated = $payments->simulate($payment, $request->validated('outcome'));
        $processed = $payments->handleCallback($simulated['payload'], $simulated['signature']);

        $flash = match ($processed->status) {
            'succeeded' => ['success', __('messages.payment.success')],
            'cancelled' => ['error', __('messages.payment.cancelled')],
            'refunded' => ['error', __('messages.payment.late_refunded')],
            default => ['error', $processed->booking->status === 'expired'
                ? __('messages.payment.expired') : __('messages.payment.failed')],
        };

        return redirect(localized_route('bookings.show', ['booking' => $processed->booking->code]))->with(...$flash);
    }
}
