<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /** Lấy bộ lọc dạng chuỗi ngắn từ query string (đã cắt độ dài, bỏ rỗng). */
    protected function filters(Request $request, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = $request->query($key);
            $out[$key] = is_scalar($value) && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, 100) : null;
        }

        return $out;
    }

    /** Danh sách enum {code, name} đã dịch theo locale. */
    protected function enumOptions(string $group, array $codes): array
    {
        return array_map(fn ($code) => ['code' => $code, 'name' => __("enums.$group.$code")], $codes);
    }
}
