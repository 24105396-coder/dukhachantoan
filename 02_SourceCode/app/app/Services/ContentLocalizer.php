<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Chuyển Markdown (hướng dẫn ứng phó, mô tả cảnh báo) → HTML an toàn
 * (HTML thô bị loại, chỉ giữ thẻ trong danh sách trắng) để frontend dùng v-html.
 */
class ContentLocalizer
{
    public const ALLOWED_TAGS = '<p><br><strong><b><em><i><ul><ol><li><blockquote><h3><h4><h5><a><code><hr><sup><sub>';

    public function toHtml(string $markdown): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Loại thuộc tính sự kiện/style nếu còn sót (phòng thủ nhiều lớp)
        return (string) preg_replace('/\s(on\w+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    }

    /** Danh sách nguồn: mảng, JSON mảng hoặc mỗi nguồn một dòng. */
    public function sources(array|string|null $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw))));
    }

    /** Văn bản thuần (đã bỏ Markdown) – dùng cho meta, SMS. */
    public function plain(?string $text, int $limit = 300): string
    {
        return Str::limit(trim(strip_tags($this->toHtml((string) $text))), $limit);
    }
}
