<?php

namespace App\Services\Notifications\Channels;

use App\Models\Notification;
use App\Services\Notifications\ChannelResult;

/** Thông báo trong ứng dụng: bản ghi đã nằm trong CSDL, "gửi" = hiển thị cho người nhận. */
class InAppChannel implements NotificationChannel
{
    public function send(Notification $notification): ChannelResult
    {
        return ChannelResult::ok('stored', 'inapp-'.$notification->id);
    }
}
