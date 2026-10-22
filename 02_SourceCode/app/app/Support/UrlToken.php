<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Mã hóa id trên URL (Tài liệu kỹ thuật Mục 6.6): Crypt::encryptString (AES-256 + MAC theo APP_KEY)
 * rồi chuyển sang base64url để không chứa "/" "+" "=" trong đoạn đường dẫn.
 * Mỗi token gắn "mục đích" (booking, contribution...) nên không dùng chéo được giữa các loại tài nguyên.
 */
class UrlToken
{
    public static function encode(string $purpose, int $id): string
    {
        return rtrim(strtr(Crypt::encryptString($purpose.':'.$id), '+/', '-_'), '=');
    }

    /** Trả id hoặc null nếu token hỏng, bị sửa, sai khóa hoặc sai mục đích. */
    public static function decode(string $purpose, string $token): ?int
    {
        // Chỉ nhận base64url hợp lệ; base64_decode không strict sẽ bỏ qua ký tự thừa nên token bị sửa đuôi vẫn giải mã được
        if ($token === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $token) || strlen($token) % 4 === 1) {
            return null;
        }
        $raw = strtr($token, '-_', '+/');
        $raw .= str_repeat('=', (4 - strlen($raw) % 4) % 4);
        if (base64_decode($raw, true) === false) {
            return null;
        }
        try {
            $plain = Crypt::decryptString($raw);
        } catch (DecryptException) {
            return null;
        }
        $prefix = $purpose.':';
        if (! str_starts_with($plain, $prefix)) {
            return null;
        }
        $id = substr($plain, strlen($prefix));

        return ctype_digit($id) && (int) $id > 0 ? (int) $id : null;
    }
}
