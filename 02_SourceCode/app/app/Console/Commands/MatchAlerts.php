<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Đối sánh cảnh báo đang hiệu lực với chặng hành trình chưa đối sánh (lịch: mỗi 5 phút).
 * Pha 1: lệnh khung để scheduler chạy không lỗi; nghiệp vụ hiện thực ở pha 2.
 */
class MatchAlerts extends Command
{
    protected $signature = 'app:match-alerts';
    protected $description = 'Đối sánh cảnh báo đang hiệu lực với chặng hành trình chưa đối sánh (lịch: mỗi 5 phút).';

    public function handle(): int
    {
        $this->line(now()->toDateTimeString().' app:match-alerts: chưa hiện thực (pha 2).');

        return self::SUCCESS;
    }
}
