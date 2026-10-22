<?php

namespace App\Http\Resources;

use App\Models\TripMember;
use Illuminate\Http\Request;

/**
 * MemberItem: số điện thoại luôn che 4 số giữa; cơ quan/quản trị có thêm `phone` đầy đủ.
 *
 * @mixin TripMember
 */
class MemberItem extends BaseResource
{
    private bool $fullPhone = false;

    public function withFullPhone(bool $show): static
    {
        $this->fullPhone = $show;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $m = $this->resource;
        $data = [
            'id' => $m->id,
            'full_name' => $m->full_name,
            'phone_masked' => $m->phone_masked,
            'nationality' => $m->nationality,
            'age_group' => $m->age_group,
            'special_needs' => $m->special_needs,
            'is_leader' => (bool) $m->is_leader,
        ];

        return $this->fullPhone ? $data + ['phone' => $m->phone] : $data;
    }
}
