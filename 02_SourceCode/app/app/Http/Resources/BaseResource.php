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

    /** Ngày giờ ISO 8601 có múi giờ (null giữ nguyên). */
    protected static function iso(mixed $value): ?string
    {
        return $value?->toIso8601String();
    }

    /** Khoảng cách km 1 chữ số thập phân. */
    protected static function km(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }
}
