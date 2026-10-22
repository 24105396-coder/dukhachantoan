<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Chuyển cảnh báo quá ends_at sang expired (lịch: mỗi 10 phút).
 * Pha 1: lệnh khung để scheduler chạy không lỗi; nghiệp vụ hiện thực ở pha 2.
 */
class ExpireAlerts extends Command
{
    protected $signature = 'app:expire-alerts';
    protected $description = 'Chuyển cảnh báo quá ends_at sang expired (lịch: mỗi 10 phút).';

    public function handle(): int
    {
        $this->line(now()->toDateTimeString().' app:expire-alerts: chưa hiện thực (pha 2).');

        return self::SUCCESS;
    }
}
