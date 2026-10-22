<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/** Thông báo chỉ người nhận được xem/đánh dấu đã đọc; gửi lại là thao tác của cơ quan/quản trị. */
class NotificationPolicy
{
    public function view(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    public function update(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    public function resend(User $user, Notification $notification): bool
    {
        return $user->isStaff() && in_array($notification->status, [Notification::STATUS_FAILED, Notification::STATUS_CANCELLED, Notification::STATUS_QUEUED], true);
    }
}
