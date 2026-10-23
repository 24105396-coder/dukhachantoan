<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/**
 * Thông báo chỉ người nhận được xem/đánh dấu đã đọc. Gửi lại: admin mọi thông báo; cơ quan toàn quốc mọi thông báo;
 * cơ quan tỉnh chỉ thông báo thuộc tỉnh mình (cùng điều kiện Notification::scopeInProvince như trang danh sách).
 */
class NotificationPolicy
{
    public const RESENDABLE = [Notification::STATUS_FAILED, Notification::STATUS_CANCELLED, Notification::STATUS_QUEUED];

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
        if (! $user->isStaff() || ! in_array($notification->status, self::RESENDABLE, true)) {
            return false;
        }
        $provinceId = $user->scopedProvinceId();

        return $provinceId === null
            || Notification::query()->whereKey($notification->getKey())->inProvince($provinceId)->exists();
    }
}
