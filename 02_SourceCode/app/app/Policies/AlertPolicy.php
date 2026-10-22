<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

/**
 * Soạn/sửa/phát cảnh báo: admin mọi cảnh báo; cơ quan tỉnh chỉ cảnh báo do mình tạo hoặc có vùng thuộc tỉnh mình
 * (cơ quan toàn quốc: mọi cảnh báo). Cảnh báo mô phỏng của kịch bản chỉ admin thao tác.
 */
class AlertPolicy
{
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Alert $alert): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if (! $user->isAgency() || $alert->scenario_id !== null) {
            return false;
        }
        if ($user->province_id === null || $alert->created_by === $user->id) {
            return true;
        }
        $provinceIds = $alert->zones()->pluck('zones.province_id')->filter()->map(fn ($id) => (int) $id)->all();

        return $provinceIds === [] || in_array((int) $user->province_id, $provinceIds, true);
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->isStaff();
    }
}
