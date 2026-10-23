<?php

namespace App\Services\Notifications\Channels;

use App\Models\Notification;
use App\Services\Notifications\ChannelResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SMS mô phỏng: chỉ ghi nhật ký; thất bại ngẫu nhiên theo SMS_SIM_FAIL_RATE (mặc định 0.1) để chứng minh thử lại.
 * Nội dung SMS cắt 160 ký tự như tin nhắn thật. Nhật ký không chứa nội dung tin và chỉ ghi số đã che (OWASP A09).
 */
class SmsSimulatorChannel implements NotificationChannel
{
    public const MAX_LENGTH = 160;

    private const MASK_KEEP_START = 3;

    private const MASK_KEEP_END = 2;

    public function send(Notification $notification): ChannelResult
    {
        $phone = $notification->user?->phone;
        if (! $phone) {
            return ChannelResult::fail('recipient has no phone number', 422);
        }
        $rate = max(0.0, min(1.0, (float) config('dukhach.notifications.sms_fail_rate', 0.1)));
        if ($rate > 0 && mt_rand() / mt_getrandmax() < $rate) {
            return ChannelResult::fail('SMS gateway simulator: temporary failure', 503);
        }
        $text = Str::limit($notification->title.' - '.$notification->body, self::MAX_LENGTH - 3);
        $ref = 'sms-'.Str::lower(Str::random(12));
        Log::channel('nghiepvu')->info('SMS mô phỏng đã gửi', [
            'notification_id' => $notification->id, 'user_id' => $notification->user_id,
            'to' => self::maskPhone($phone), 'ref' => $ref, 'length' => mb_strlen($text),
        ]);

        return ChannelResult::ok('accepted', $ref, 202);
    }

    /** Che số cho nhật ký: giữ 3 số đầu và 2 số cuối (0994617421 → 099*****21). */
    public static function maskPhone(string $phone): string
    {
        $length = mb_strlen($phone);
        $keep = self::MASK_KEEP_START + self::MASK_KEEP_END;
        if ($length <= $keep) {
            return str_repeat('*', $length);
        }

        return mb_substr($phone, 0, self::MASK_KEEP_START).str_repeat('*', $length - $keep).mb_substr($phone, -self::MASK_KEEP_END);
    }
}
