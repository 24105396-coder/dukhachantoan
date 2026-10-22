<?php

namespace App\Policies;

use App\Models\IncidentReport;
use App\Models\User;

/** Duyệt sự cố: admin hoặc cơ quan phụ trách tỉnh của vùng sự cố. */
class IncidentReportPolicy
{
    public function review(User $user, IncidentReport $incident): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if (! $user->isAgency()) {
            return false;
        }
        $provinceId = $incident->zone?->province_id;

        return $user->province_id === null || ($provinceId !== null && (int) $provinceId === (int) $user->province_id);
    }
}
