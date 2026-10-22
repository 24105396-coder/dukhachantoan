<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource gốc: luôn trả mảng PHP thuần (đã resolve) để Inertia và API dùng chung,
 * tránh JsonResource lồng nhau bị bọc "data".
 */
abstract class BaseResource extends JsonResource
{
    use Concerns\FormatsHeritage;

    public static function one(mixed $model): ?array
    {
        return $model ? (new static($model))->resolve(request()) : null;
    }

    public static function list(iterable $items): array
    {
        return collect($items)->map(fn ($m) => static::one($m))->filter()->values()->all();
    }

    /** Hình dạng phân trang chuẩn Laravel toArray(): data[], current_page, last_page, per_page, total, links[]. */
    public static function paginate(LengthAwarePaginator $paginator): array
    {
        return $paginator->through(fn ($m) => static::one($m))->toArray();
    }
}
