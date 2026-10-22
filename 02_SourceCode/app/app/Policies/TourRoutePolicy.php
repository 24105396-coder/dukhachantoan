<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\TourRoute;
use App\Models\User;

/** Contributor chỉ quản lý tuyến do mình tạo; admin/manager quản lý mọi tuyến. */
class TourRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ADMIN, Role::MANAGER, Role::CONTRIBUTOR);
    }

    public function view(User $user, TourRoute $route): bool
    {
        return $this->update($user, $route);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, TourRoute $route): bool
    {
        return $user->isStaff() || ($user->hasRole(Role::CONTRIBUTOR) && (int) $route->created_by === (int) $user->id);
    }

    public function delete(User $user, TourRoute $route): bool
    {
        return $this->update($user, $route);
    }

    public function manageSlots(User $user, TourRoute $route): bool
    {
        return $this->update($user, $route);
    }
}
