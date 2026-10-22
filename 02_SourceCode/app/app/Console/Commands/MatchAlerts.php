<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Services\MatchingService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/** Đối sánh mọi cảnh báo đang hiệu lực với hành trình (kể cả hành trình mới) và xếp thông báo cho đối sánh mới. */
class MatchAlerts extends Command
{
    protected $signature = 'app:match-alerts {--alert= : Chỉ đối sánh một mã cảnh báo}';
    protected $description = 'Đối sánh cảnh báo đang hiệu lực với hành trình và xếp thông báo (lịch: mỗi 5 phút).';

    public function handle(MatchingService $matching, NotificationService $notifications): int
    {
        $alerts = Alert::query()->live()->with('zones')
            ->when($this->option('alert'), fn ($q, $code) => $q->where('code', $code))->orderByDesc('severity')->get();
        $matches = 0;
        $queued = 0;
        foreach ($alerts as $alert) {
            $new = $matching->matchAlert($alert);
            $matches += $new->count();
            $queued += $notifications->enqueueMatches($new);
        }
        $this->line(sprintf('%s app:match-alerts alerts=%d new_matches=%d notifications=%d', now()->toDateTimeString(), $alerts->count(), $matches, $queued));

        return self::SUCCESS;
    }
}
