<?php

namespace App\Services\Queries;

use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\Interpretation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Truy vấn lọc dùng chung cho trang web và API mở. Mọi giá trị lọc đi qua binding,
 * thứ tự sắp xếp chỉ chọn trong danh sách cố định.
 */
class HeritageQuery
{
    /** Giá trị sort hợp lệ của trang di sản; giá trị lạ dùng thứ tự mặc định. */
    public const SITE_SORTS = ['newest', 'name', 'popular', 'oldest'];

    public static function sites(array $f): Builder
    {
        $query = HeritageSite::query()->published();

        self::keyword($query, $f['q'] ?? null);
        $query->when($f['province'] ?? null, fn ($q, $v) => $q->whereHas('province', fn ($p) => $p->where('code', $v)))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('site_type', $v))
            ->when($f['level'] ?? null, fn ($q, $v) => $q->where('recognition_level', $v))
            ->when($f['period'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('code', $v)))
            ->when($f['tag'] ?? null, fn ($q, $v) => $q->withTag($v));

        return self::sortSites($query, $f['sort'] ?? null);
    }

    private static function sortSites(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'popular' => $query->orderByDesc('view_count')->orderBy('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'oldest', 'year' => $query->orderByRaw('year_built IS NULL')->orderBy('year_built')->orderBy('id'),
            'name' => $query->orderBy(self::localizedTitle('site', 'heritage_sites'))->orderBy('slug'),
            default => $query->orderByRaw("FIELD(recognition_level, 'unesco', 'quoc_gia_dac_biet', 'quoc_gia', 'cap_tinh', 'chua_xep_hang')")
                ->orderByDesc('view_count')->orderBy('id'),
        };
    }

    /** Subquery tiêu đề độ sâu 1 theo locale hiện tại (tham số hóa) để sắp xếp theo tên. */
    private static function localizedTitle(string $morph, string $table): Builder
    {
        return Interpretation::query()->select('title')
            ->whereColumn('interpretations.subject_id', $table.'.id')
            ->where('interpretations.subject_type', $morph)
            ->where('interpretations.depth_level', 1)
            ->where('interpretations.locale', app()->getLocale())
            ->limit(1);
    }

    public static function artifacts(array $f): Builder
    {
        $query = Artifact::query()->published()->whereHas('site', fn ($s) => $s->published());

        self::keyword($query, $f['q'] ?? null);
        $query->when($f['type'] ?? null, fn ($q, $v) => $q->where('artifact_type', $v))
            ->when($f['site'] ?? null, fn ($q, $v) => $q->whereHas('site', fn ($s) => $s->where('slug', $v)))
            ->when(($f['treasure'] ?? null) !== null, fn ($q) => $q->where('is_national_treasure', filter_var($f['treasure'], FILTER_VALIDATE_BOOLEAN)))
            ->when($f['period'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('code', $v)))
            ->when($f['tag'] ?? null, fn ($q, $v) => $q->withTag($v));

        return $query->orderByDesc('is_national_treasure')->orderByDesc('view_count')->orderBy('id');
    }

    public static function figures(array $f): Builder
    {
        $query = HistoricalFigure::query()->published();

        self::keyword($query, $f['q'] ?? null);
        $query->when($f['type'] ?? null, fn ($q, $v) => $q->where('figure_type', $v))
            ->when($f['period'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('code', $v)))
            ->when($f['tag'] ?? null, fn ($q, $v) => $q->withTag($v));

        return $query->orderByRaw('birth_year IS NULL')->orderBy('birth_year')->orderBy('id');
    }

    /** Tìm theo tiêu đề/tóm tắt diễn giải (mọi ngôn ngữ) hoặc slug, LIKE tham số hóa. */
    public static function keyword(Builder $query, ?string $q): void
    {
        if ($q === null || trim($q) === '') {
            return;
        }
        $like = like_contains(trim($q));
        $query->where(fn ($w) => $w->where('slug', 'like', $like)
            ->orWhereHas('interpretations', fn ($i) => $i->where(fn ($x) => $x->where('title', 'like', $like)->orWhere('summary', 'like', $like))));
    }
}
