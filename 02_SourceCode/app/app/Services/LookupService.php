<?php

namespace App\Services;

use App\Http\Resources\TagItemResource;
use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\Period;
use App\Models\Province;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Danh mục tham chiếu đã địa phương hóa dùng cho bộ lọc và form. */
class LookupService
{
    public function stats(): array
    {
        return Cache::remember('stats:overview', 600, fn () => [
            'sites' => HeritageSite::published()->count(),
            'artifacts' => Artifact::published()->count(),
            'figures' => HistoricalFigure::published()->count(),
            'provinces' => HeritageSite::published()->distinct()->count('province_id'),
            'intangible' => HeritageSite::published()->where('site_type', 'di_san_phi_vat_the')->count(),
        ]);
    }

    /** [{code, name, count, lat, lng}] – chỉ tỉnh có di sản công bố khi $onlyWithSites. */
    public function provinces(bool $onlyWithSites = false): array
    {
        $counts = HeritageSite::published()->select('province_id', DB::raw('COUNT(*) as total'))
            ->groupBy('province_id')->pluck('total', 'province_id');

        return Province::query()->orderBy('name')->get()
            ->map(fn (Province $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->localizedName(),
                'region' => $p->region,
                'lat' => (float) $p->lat,
                'lng' => (float) $p->lng,
                'count' => (int) ($counts[$p->id] ?? 0),
            ])
            ->when($onlyWithSites, fn ($c) => $c->filter(fn ($p) => $p['count'] > 0))
            ->values()->all();
    }

    public function periods(): array
    {
        return Period::query()->with('translations')->orderBy('sort_order')->get()
            ->map(fn (Period $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'start_year' => $p->start_year,
                'end_year' => $p->end_year,
            ])->all();
    }

    public function tags(?string $group = null): array
    {
        $tags = Tag::query()->with('translations')
            ->when($group, fn ($q, $g) => $q->where('tag_group', $g))
            ->orderByDesc('usage_count')->orderBy('slug')->get();

        return TagItemResource::list($tags);
    }

    /** [{id, title}] cho ô chọn trong form quản trị. */
    public function options(string $class): array
    {
        return $class::query()->with(['interpretations' => fn ($q) => $q->where('depth_level', 1)])
            ->orderBy('slug')->get()
            ->map(fn ($m) => ['id' => $m->id, 'slug' => $m->slug, 'title' => $m->title])->all();
    }
}
