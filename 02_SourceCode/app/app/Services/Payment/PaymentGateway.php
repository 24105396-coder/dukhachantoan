<?php

namespace App\Services\Payment;

use App\Models\Payment;

/** Hợp đồng cổng thanh toán; hiện chỉ có cổng mô phỏng (sandbox). */
interface PaymentGateway
{
    public function name(): string;

    public function createTxnRef(): string;

    /** Tạo dữ liệu kết quả giao dịch giống IPN của cổng thật. */
    public function buildPayload(Payment $payment, string $outcome): array;

    public function sign(array $payload): string;

    public function verify(array $payload, string $signature): bool;
}
