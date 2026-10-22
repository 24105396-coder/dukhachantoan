<?php

namespace App\Console\Commands;

use App\Services\AnalyticsClient;
use Illuminate\Console\Command;

/**
 * Yêu cầu dịch vụ Python dựng lại chỉ mục tìm kiếm (POST /reindex) để đồng bộ nội dung mới.
 * Dịch vụ tắt thì báo lỗi nhưng không ảnh hưởng web (tìm kiếm tự dùng FULLTEXT dự phòng).
 */
class ReindexSearch extends Command
{
    protected $signature = 'app:reindex-search {--timeout=30 : Thời gian chờ tối đa (giây)}';
    protected $description = 'Rebuild the analytics search index via POST /reindex';

    public function handle(AnalyticsClient $analytics): int
    {
        $result = $analytics->reindex((float) $this->option('timeout'));
        if ($result === null) {
            $this->error('Analytics reindex failed: service unavailable');

            return self::FAILURE;
        }
        $this->info(sprintf('Analytics reindex: indexed=%d took_ms=%s', (int) ($result['indexed'] ?? 0), $result['took_ms'] ?? '?'));

        return self::SUCCESS;
    }
}
