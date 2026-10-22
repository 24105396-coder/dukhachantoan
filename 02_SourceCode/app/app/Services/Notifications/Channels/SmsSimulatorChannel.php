<?php

namespace App\Services\Notifications\Channels;

use App\Models\Notification;
use App\Services\Notifications\ChannelResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SMS mô phỏng: chỉ ghi nhật ký; thất bại ngẫu nhiên theo SMS_SIM_FAIL_RATE (mặc định 0.1) để chứng minh thử lại.
 * Nội dung SMS cắt 160 ký tự như tin nhắn thật.
 */
class SmsSimulatorChannel implements NotificationChannel
{
    public const MAX_LENGTH = 160;

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
        Log::channel('nghiepvu')->info('SMS mô phỏng đã gửi', ['to' => $phone, 'ref' => $ref, 'text' => $text]);

        return ChannelResult::ok('accepted', $ref, 202);
    }
}
