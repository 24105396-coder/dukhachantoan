<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Bộ đệm ngắn (mặc định 60 s) cho dữ liệu trang công khai không phụ thuộc người dùng
 * (trang chủ, danh sách di sản không lọc, chi tiết di sản, danh mục lọc) để giảm truy vấn khi tải cao.
 *
 * Khóa gồm phiên bản nội dung + locale + scheme/host (URL tuyệt đối trong dữ liệu); mọi thay đổi nội dung
 * (observer FlushPageCache, HeritageWriter) tăng phiên bản → toàn bộ khóa cũ tự hết hiệu lực, không cần Cache::flush
 * (flush sẽ xóa cả bộ đếm rate limit). TTL ≤ 0 tắt bộ đệm.
 */
class PageCache
{
    private const VERSION_KEY = 'page-cache:version';

    public function remember(string $key, Closure $callback): mixed
    {
        $ttl = $this->ttl();
        if ($ttl <= 0) {
            return $callback();
        }

        return $this->store()->remember($this->key($key), $ttl, $callback);
    }

    /** Vô hiệu toàn bộ dữ liệu trang đã đệm (gọi khi nội dung thay đổi). */
    public function flush(): void
    {
        try {
            $store = $this->store();
            if ($store->get(self::VERSION_KEY) === null) {
                $store->forever(self::VERSION_KEY, 1);
            }
            $store->increment(self::VERSION_KEY);
        } catch (\Throwable $e) {
            // Không để lỗi bộ đệm chặn thao tác ghi nội dung; TTL ngắn giới hạn dữ liệu cũ
            report($e);
        }
    }

    public function ttl(): int
    {
        return (int) config('disanso.page_cache.ttl', 60);
    }

    private function key(string $key): string
    {
        $version = (int) ($this->store()->get(self::VERSION_KEY) ?? 0);
        $request = request();
        $host = $request ? $request->getSchemeAndHttpHost() : config('app.url');

        return 'page:'.$version.':'.app()->getLocale().':'.md5($host).':'.$key;
    }

    private function store(): Repository
    {
        return Cache::store(config('disanso.page_cache.store') ?: null);
    }
}
