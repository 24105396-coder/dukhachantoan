<?php

namespace App\Services\Notifications;

/** Kết quả một lần gửi qua kênh: thành công/thất bại, mã phản hồi, nội dung, mã tham chiếu nhà cung cấp. */
final class ChannelResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly int $code,
        public readonly string $body,
        public readonly ?string $providerRef = null,
    ) {
    }

    public static function ok(string $body = 'ok', ?string $ref = null, int $code = 200): self
    {
        return new self(true, $code, $body, $ref);
    }

    public static function fail(string $error, int $code = 500): self
    {
        return new self(false, $code, $error);
    }
}
