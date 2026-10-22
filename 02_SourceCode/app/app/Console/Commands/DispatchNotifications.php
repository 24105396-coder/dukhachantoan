<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

/** Gửi thông báo đến hạn, thử lại lũy tiến 1/5/15/60/240 phút, nhắc lại mức 4 chưa đọc (lịch: mỗi phút). */
class DispatchNotifications extends Command
{
    protected $signature = 'app:dispatch-notifications {--limit= : Số thông báo tối đa mỗi lô}';
    protected $description = 'Gửi thông báo đến hạn qua in-app/email/SMS mô phỏng, có thử lại (lịch: mỗi phút).';

    public function handle(NotificationService $notifications): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $stats = $notifications->dispatchDue($limit);
        $this->line(now()->toDateTimeString().' app:dispatch-notifications '.collect($stats)->map(fn ($v, $k) => "$k=$v")->implode(' '));

        return self::SUCCESS;
    }
}
