<?php

namespace App\Services;

use App\Models\Period;

/**
 * Suy ra thời kỳ của một sự kiện theo năm: năm ∈ [start_year, end_year] (end_year null = đến nay).
 * Thời kỳ của chủ thể (di sản/nhân vật) chỉ được ưu tiên khi chứa năm đó, ví dụ sự kiện Chăm Pa năm 500
 * giữ "champa" thay vì "bac_thuoc"; còn lại lấy thời kỳ khớp có sort_order nhỏ nhất.
 * Nhờ vậy sự kiện "công nhận 2010" của di tích thời Lý thuộc "hien_dai" (kiểm tra Q08 của dịch vụ Python).
 */
class PeriodResolver
{
    /** @var array<int, array{id:int,start:int,end:?int}>|null */
    private ?array $ranges = null;

    public function forYear(?int $year, ?int $preferredId = null): ?int
    {
        if ($year === null) {
            return null;
        }
        $matches = array_values(array_filter($this->ranges(),
            fn (array $p) => $year >= $p['start'] && ($p['end'] === null || $year <= $p['end'])));
        foreach ($matches as $p) {
            if ($p['id'] === $preferredId) {
                return $p['id'];
            }
        }

        return $matches[0]['id'] ?? null;
    }

    /** @return array<int, array{id:int,start:int,end:?int}> */
    private function ranges(): array
    {
        return $this->ranges ??= Period::query()->orderBy('sort_order')->orderBy('start_year')
            ->get(['id', 'start_year', 'end_year'])
            ->map(fn (Period $p) => [
                'id' => (int) $p->id,
                'start' => (int) $p->start_year,
                'end' => $p->end_year === null ? null : (int) $p->end_year,
            ])->all();
    }
}
