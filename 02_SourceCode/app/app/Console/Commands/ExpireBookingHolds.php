<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

/** Giải phóng các phiếu giữ chỗ đã quá hạn, trả chỗ về khung giờ. */
class ExpireBookingHolds extends Command
{
    protected $signature = 'app:expire-holds';
    protected $description = 'Release expired booking holds back to the slot capacity';

    public function handle(BookingService $service): int
    {
        $count = $service->expireStaleHolds();
        $this->info("Released {$count} expired hold(s).");

        return self::SUCCESS;
    }
}
