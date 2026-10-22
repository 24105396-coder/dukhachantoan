<?php

namespace App\Observers;

use App\Services\PageCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Nội dung công khai thay đổi (admin, seeder, kiểm duyệt, đặt chỗ làm đổi khung giờ) → tăng phiên bản PageCache.
 * Tăng view_count dùng Query Builder nên không kích hoạt observer, bộ đệm không bị xóa theo từng lượt xem.
 */
class FlushPageCache
{
    public function saved(Model $model): void
    {
        app(PageCache::class)->flush();
    }

    public function deleted(Model $model): void
    {
        app(PageCache::class)->flush();
    }

    public function restored(Model $model): void
    {
        app(PageCache::class)->flush();
    }
}
