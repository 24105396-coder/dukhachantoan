<?php

namespace App\Services\Payment;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public static function invalidSignature(): self
    {
        return new self('Invalid signature', 400);
    }

    public static function unknownTransaction(): self
    {
        return new self('Unknown transaction', 400);
    }

    public static function amountMismatch(): self
    {
        return new self('Amount mismatch', 400);
    }
}
