<?php

namespace App\Console\Commands;

use App\Models\PageView;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Cộng các lượt xem chưa tính (page_views.counted_at NULL) vào cột view_count của di sản/hiện vật/nhân vật.
 * Mỗi lượt chạy xử lý tối đa BATCH dòng theo id; đánh dấu đúng các id đã đếm trong cùng transaction.
 */
class SyncViewCounts extends Command
{
    private const BATCH = 5000;
    private const COUNTED_TYPES = ['site', 'artifact', 'figure'];

    protected $signature = 'app:sync-view-counts';
    protected $description = 'Add pending page views to view_count columns';

    public function handle(): int
    {
        $ids = PageView::query()->whereNull('counted_at')->orderBy('id')->limit(self::BATCH)->pluck('id')->all();
        if ($ids === []) {
            return self::SUCCESS;
        }

        $total = DB::transaction(function () use ($ids) {
            $groups = PageView::query()->whereIn('id', $ids)
                ->selectRaw('subject_type, subject_id, COUNT(*) as views')
                ->groupBy('subject_type', 'subject_id')->get();

            foreach ($groups as $group) {
                $this->increment($group->subject_type, (int) $group->subject_id, (int) $group->views);
            }
            PageView::query()->whereIn('id', $ids)->update(['counted_at' => now()]);

            return $groups->sum('views');
        });

        $this->info("Synced {$total} page views");

        return self::SUCCESS;
    }

    private function increment(string $type, int $id, int $views): void
    {
        $class = Relation::getMorphedModel($type);
        if (! in_array($type, self::COUNTED_TYPES, true) || $class === null) {
            return;
        }
        // Query Builder: không đổi updated_at, không kích hoạt observer xóa bộ đệm trang
        DB::table((new $class())->getTable())->where('id', $id)->increment('view_count', $views);
    }
}
