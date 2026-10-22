<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Điểm nhận IPN (server-to-server) của cổng sandbox. Chữ ký sai → 400 (xử lý ở bootstrap/app.php).
 */
class PaymentIpnController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $payload = $request->validate([
            'txn_ref' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'outcome' => ['required', 'in:success,fail,cancel'],
            'response_code' => ['nullable', 'string', 'max:4'],
            'pay_date' => ['nullable', 'string', 'max:20'],
            'nonce' => ['nullable', 'string', 'max:64'],
        ]);
        $signature = (string) $request->input('signature', $request->header('X-Signature', ''));

        // Ký trên toàn bộ trường payload đã gửi (trừ chữ ký)
        $payment = $payments->handleCallback(array_filter($payload, fn ($v) => $v !== null), $signature);

        return response()->json(['txn_ref' => $payment->txn_ref, 'status' => $payment->status, 'booking_status' => $payment->booking->status]);
    }
}
