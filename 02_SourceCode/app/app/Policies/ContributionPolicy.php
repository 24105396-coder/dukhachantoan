<?php

namespace App\Policies;

use App\Models\Contribution;
use App\Models\User;

class ContributionPolicy
{
    public function view(User $user, Contribution $contribution): bool
    {
        return (int) $contribution->user_id === (int) $user->id || $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->canContribute();
    }

    public function review(User $user, Contribution $contribution): bool
    {
        return $user->isStaff();
    }
}
