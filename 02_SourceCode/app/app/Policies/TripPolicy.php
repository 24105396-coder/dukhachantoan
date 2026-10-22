<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

/**
 * Hành trình: chủ và đơn vị lữ hành đăng ký được xem/sửa/hủy/báo an toàn; cơ quan hỗ trợ xem hành trình
 * đi qua tỉnh mình (null = toàn quốc); admin xem mọi hành trình. Sửa/hủy chỉ khi còn draft|active.
 */
class TripPolicy
{
    public function view(User $user, Trip $trip): bool
    {
        if ($user->isAdmin() || $trip->isManagedBy($user)) {
            return true;
        }
        if ($user->isAgency()) {
            return $user->province_id === null || in_array((int) $user->province_id, $trip->provinceIds(), true);
        }

        return false;
    }

    public function update(User $user, Trip $trip): bool
    {
        return $trip->isManagedBy($user) && $trip->isEditable();
    }

    public function cancel(User $user, Trip $trip): bool
    {
        return $trip->isManagedBy($user) && $trip->isEditable();
    }

    public function checkin(User $user, Trip $trip): bool
    {
        return $trip->isManagedBy($user) && $trip->status !== Trip::STATUS_CANCELLED;
    }

    /** Xác nhận đã biết cảnh báo: chủ/đơn vị lữ hành. */
    public function acknowledge(User $user, Trip $trip): bool
    {
        return $trip->isManagedBy($user);
    }
}
