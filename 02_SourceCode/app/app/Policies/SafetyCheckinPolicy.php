<?php

namespace App\Policies;

use App\Models\SafetyCheckin;
use App\Models\User;

/** Phản hồi check-in: admin hoặc cơ quan phụ trách tỉnh có hành trình đi qua. */
class SafetyCheckinPolicy
{
    public function respond(User $user, SafetyCheckin $checkin): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if (! $user->isAgency()) {
            return false;
        }

        return $user->province_id === null
            || in_array((int) $user->province_id, $checkin->trip?->provinceIds() ?? [], true);
    }
}
