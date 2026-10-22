<?php

namespace Database\Seeders\Support;

/**
 * Sinh ảnh SVG minh họa (gradient theo loại đối tượng + tiêu đề) thay cho ảnh thật chưa rõ bản quyền.
 * Chữ dùng phông sans có sẵn trên mọi hệ điều hành vì SVG tải qua <img> không dùng được web font của trang;
 * phông serif dự phòng thiếu glyph tiếng Việt tổ hợp sẵn nên dấu bị tách ("Quâ`n thê").
 */
class SvgIllustration
{
    public const PALETTES = [
        'site' => [['#f4c542', '#b3261e'], ['#e9a23b', '#7a1f12']],
        'artifact' => [['#c58b4a', '#5a3215'], ['#d9a86c', '#3e2410']],
        'figure' => [['#3f51b5', '#00897b'], ['#283593', '#26a69a']],
        'route' => [['#2e7d32', '#f9a825'], ['#1b5e20', '#ff8f00']],
    ];

    public static function make(string $type, string $title, int $variant = 1, string $subtitle = 'Ảnh minh họa – DiSanSo'): string
    {
        $palettes = self::PALETTES[$type] ?? self::PALETTES['site'];
        [$from, $to] = $palettes[($variant - 1) % count($palettes)];
        $id = 'g'.$variant;
        $lines = self::wrap($title, 26, 3);
        $startY = 400 - (count($lines) - 1) * 40;
        $text = '';
        foreach ($lines as $i => $line) {
            $y = $startY + $i * 80;
            $text .= '<text x="600" y="'.$y.'" text-anchor="middle" font-family="\'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'DejaVu Sans\', sans-serif" font-size="60" font-weight="bold" fill="#ffffff">'
                .htmlspecialchars($line, ENT_QUOTES | ENT_XML1, 'UTF-8').'</text>';
        }
        $sub = htmlspecialchars($subtitle, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $angle = $variant === 1 ? 'x1="0" y1="0" x2="1" y2="1"' : 'x1="1" y1="0" x2="0" y2="1"';
        $deco = $variant === 1
            ? '<circle cx="1050" cy="150" r="220" fill="#ffffff" opacity="0.08"/><circle cx="120" cy="720" r="160" fill="#ffffff" opacity="0.06"/>'
            : '<polygon points="0,800 300,520 600,800" fill="#ffffff" opacity="0.07"/><polygon points="600,800 950,450 1200,800" fill="#ffffff" opacity="0.09"/>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800" role="img" aria-label="'.htmlspecialchars($title, ENT_QUOTES | ENT_XML1, 'UTF-8').'">'
            .'<defs><linearGradient id="'.$id.'" '.$angle.'><stop offset="0" stop-color="'.$from.'"/><stop offset="1" stop-color="'.$to.'"/></linearGradient></defs>'
            .'<rect width="1200" height="800" fill="url(#'.$id.')"/>'.$deco
            .'<rect x="60" y="60" width="1080" height="680" fill="none" stroke="#ffffff" stroke-opacity="0.35" stroke-width="3" rx="18"/>'
            .$text
            .'<text x="600" y="690" text-anchor="middle" font-family="\'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'DejaVu Sans\', sans-serif" font-size="30" fill="#ffffff" opacity="0.85">'.$sub.'</text>'
            .'</svg>';
    }

    /** Ngắt dòng theo số ký tự (đa byte), tối đa $maxLines dòng. */
    private static function wrap(string $text, int $width, int $maxLines): array
    {
        $lines = [];
        $current = '';
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) > $width && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim(mb_substr($lines[$maxLines - 1], 0, $width - 1)).'…';
        }

        return $lines ?: [''];
    }
}
