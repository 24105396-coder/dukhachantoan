<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Cổng thanh toán mô phỏng: ký HMAC-SHA256 trên chuỗi tham số đã sắp xếp (giống VNPay).
 */
class SandboxGateway implements PaymentGateway
{
    public const OUTCOME_CODES = ['success' => '00', 'fail' => '51', 'cancel' => '24'];

    public function name(): string
    {
        return 'sandbox';
    }

    public function createTxnRef(): string
    {
        return 'SB-'.now()->format('ymdHis').'-'.Str::upper(Str::random(6));
    }

    public function buildPayload(Payment $payment, string $outcome): array
    {
        return [
            'txn_ref' => $payment->txn_ref,
            'amount' => (int) $payment->amount_vnd,
            'currency' => $payment->currency ?? 'VND',
            'outcome' => $outcome,
            'response_code' => self::OUTCOME_CODES[$outcome] ?? '99',
            'pay_date' => now()->format('YmdHis'),
            'nonce' => Str::random(16),
        ];
    }

    public function sign(array $payload): string
    {
        return hash_hmac('sha256', $this->canonical($payload), $this->secret());
    }

    public function verify(array $payload, string $signature): bool
    {
        // Fail-closed: thiếu khóa thì không chấp nhận chữ ký nào
        return $signature !== '' && $this->secret() !== '' && hash_equals($this->sign($payload), $signature);
    }

    private function canonical(array $payload): string
    {
        unset($payload['signature']);
        ksort($payload);

        return http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    }

    private function secret(): string
    {
        return (string) config('disanso.payment.sandbox_secret');
    }
}
