<?php

namespace App\Models\Concerns;

/**
 * Route quản trị khai báo {site} không chỉ định cột: chấp nhận cả id số (frontend
 * quản trị dùng id) lẫn khóa route mặc định (slug/code). Route công khai dùng
 * {site:slug} nên không bị ảnh hưởng.
 */
trait ResolvesByIdOrKey
{
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field === null && is_scalar($value) && ctype_digit((string) $value)) {
            return $this->newQuery()->whereKey((int) $value)->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
