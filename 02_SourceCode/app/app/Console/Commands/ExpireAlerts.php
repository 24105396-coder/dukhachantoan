<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

/** Cảnh báo quá ends_at → expired, hủy thông báo đang xếp hàng (lịch: mỗi 10 phút). */
class ExpireAlerts extends Command
{
    protected $signature = 'app:expire-alerts';
    protected $description = 'Chuyển cảnh báo quá hạn sang expired (lịch: mỗi 10 phút).';

    public function handle(AlertService $alerts): int
    {
        $this->line(sprintf('%s app:expire-alerts expired=%d', now()->toDateTimeString(), $alerts->expireDue()));

        return self::SUCCESS;
    }
}
