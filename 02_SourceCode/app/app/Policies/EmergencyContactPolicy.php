<?php

namespace App\Policies;

use App\Models\EmergencyContact;
use App\Models\User;

/** Danh bạ: admin mọi mục; cơ quan tỉnh chỉ mục của tỉnh mình (số toàn quốc chỉ admin/cơ quan toàn quốc). */
class EmergencyContactPolicy
{
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, EmergencyContact $contact): bool
    {
        if ($user->isAdmin() || ($user->isAgency() && $user->province_id === null)) {
            return true;
        }

        return $user->isAgency() && $contact->province_id !== null && (int) $contact->province_id === (int) $user->province_id;
    }

    public function delete(User $user, EmergencyContact $contact): bool
    {
        return $this->update($user, $contact);
    }
}
