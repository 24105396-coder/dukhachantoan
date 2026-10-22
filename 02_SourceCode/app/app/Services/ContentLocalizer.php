<?php

namespace App\Services;

use App\Models\Interpretation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Chọn bản diễn giải theo locale + độ sâu và chuyển Markdown → HTML an toàn
 * (HTML thô bị loại, chỉ giữ thẻ trong danh sách trắng) để frontend dùng v-html.
 */
class ContentLocalizer
{
    public const ALLOWED_TAGS = '<p><br><strong><b><em><i><ul><ol><li><blockquote><h3><h4><h5><a><code><hr><sup><sub>';

    public function normalizeDepth(mixed $depth): int
    {
        $value = (int) $depth;

        return $value >= 1 && $value <= 3 ? $value : 1;
    }

    public function pick(Model $subject, int $depth = 1, ?string $locale = null): ?Interpretation
    {
        return method_exists($subject, 'interpretation') ? $subject->interpretation($locale, $depth) : null;
    }

    /** Dữ liệu diễn giải cho trang chi tiết (SiteDetail.interpretation, …). */
    public function interpretation(Model $subject, int $depth = 1, ?string $locale = null): ?array
    {
        $item = $this->pick($subject, $depth, $locale);
        if (! $item) {
            return null;
        }

        return [
            'depth_level' => $item->depth_level,
            'locale' => $item->locale,
            'title' => $item->title,
            'summary' => $item->summary,
            'body_html' => $this->toHtml((string) $item->body),
            'sources' => $this->sources($item->sources),
            'is_machine_translated' => (bool) $item->is_machine_translated,
            'version' => $item->version,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

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

    /** Cột sources lưu mỗi nguồn một dòng (hoặc JSON mảng). */
    public function sources(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw))));
    }

    /** Văn bản thuần (đã bỏ Markdown) – dùng cho mô tả JSON-LD, meta. */
    public function plain(?string $text, int $limit = 300): string
    {
        return Str::limit(trim(strip_tags($this->toHtml((string) $text))), $limit);
    }
}
