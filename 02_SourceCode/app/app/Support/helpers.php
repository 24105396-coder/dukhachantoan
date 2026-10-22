<?php

if (! function_exists('localized_route')) {
    /**
     * Sinh URL tuyệt đối có tiền tố locale (mặc định locale hiện tại).
     * Tham số `locale` trong $params ghi đè locale hiện tại.
     */
    function localized_route(string $name, mixed $params = [], bool $absolute = true): string
    {
        $params = is_array($params) ? $params : [$params];
        $locale = $params['locale'] ?? app()->getLocale();
        unset($params['locale']);
        if (! in_array($locale, ['vi', 'en'], true)) {
            $locale = config('app.locale', 'vi');
        }

        return route($name, array_merge(['locale' => $locale], $params), $absolute);
    }
}

if (! function_exists('like_escape')) {
    /** Thoát ký tự đại diện của LIKE (\ % _) để chuỗi người dùng chỉ khớp nguyên văn. */
    function like_escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

if (! function_exists('like_contains')) {
    /** Mẫu LIKE "chứa chuỗi" đã thoát ký tự đại diện. */
    function like_contains(string $value): string
    {
        return '%'.like_escape($value).'%';
    }
}
