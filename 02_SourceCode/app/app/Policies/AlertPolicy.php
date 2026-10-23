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

        return $alert->scenario_id === null && $this->inAgencyScope($user, $alert);
    }

    /** Xem chi tiết quản trị (đối sánh, thống kê thông báo): cùng phạm vi tỉnh như sửa; cảnh báo mô phỏng vẫn xem được. */
    public function view(User $user, Alert $alert): bool
    {
        return $user->isAdmin() || $this->inAgencyScope($user, $alert);
    }

    /** Cơ quan toàn quốc, người tạo, hoặc cảnh báo có vùng thuộc tỉnh mình (cảnh báo chưa gắn vùng: mọi cơ quan). */
    private function inAgencyScope(User $user, Alert $alert): bool
    {
        if (! $user->isAgency()) {
            return false;
        }
        if ($user->province_id === null || $alert->created_by === $user->id) {
            return true;
        }
        $provinceIds = $alert->zones()->pluck('zones.province_id')->filter()->map(fn ($id) => (int) $id)->all();

        return $provinceIds === [] || in_array((int) $user->province_id, $provinceIds, true);
    }
}
