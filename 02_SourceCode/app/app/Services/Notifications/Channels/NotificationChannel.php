<?php

namespace App\Services\Notifications\Channels;

use App\Models\Notification;
use App\Services\Notifications\ChannelResult;

/** Một kênh gửi thông báo (in-app, email, SMS mô phỏng). Không ném ngoại lệ: lỗi trả ChannelResult::fail. */
interface NotificationChannel
{
    public function send(Notification $notification): ChannelResult;
}
