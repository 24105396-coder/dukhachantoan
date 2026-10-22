<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Gửi thông báo đến hạn, thử lại lũy tiến, nhắc lại mức 4 chưa đọc (lịch: mỗi phút).
 * Pha 1: lệnh khung để scheduler chạy không lỗi; nghiệp vụ hiện thực ở pha 2.
 */
class DispatchNotifications extends Command
{
    protected $signature = 'app:dispatch-notifications';
    protected $description = 'Gửi thông báo đến hạn, thử lại lũy tiến, nhắc lại mức 4 chưa đọc (lịch: mỗi phút).';

    public function handle(): int
    {
        $this->line(now()->toDateTimeString().' app:dispatch-notifications: chưa hiện thực (pha 2).');

        return self::SUCCESS;
    }
}
