<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /** Chủ booking, nhân viên, hoặc contributor sở hữu tuyến được xem. */
    public function view(User $user, Booking $booking): bool
    {
        if ($this->owns($user, $booking) || $user->isStaff()) {
            return true;
        }
        $booking->loadMissing('slot.route');

        return (int) $booking->slot?->route?->created_by === (int) $user->id;
    }

    public function pay(User $user, Booking $booking): bool
    {
        return $this->owns($user, $booking);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $this->owns($user, $booking) || $user->isStaff();
    }

    public function updateStatus(User $user, Booking $booking): bool
    {
        return $user->isStaff();
    }

    private function owns(User $user, Booking $booking): bool
    {
        return (int) $booking->user_id === (int) $user->id;
    }
}
