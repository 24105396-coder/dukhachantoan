<?php

namespace App\Http\Controllers;

use App\Http\Resources\SiteCardResource;
use App\Http\Resources\SiteDetailResource;
use App\Models\HeritageSite;
use App\Services\ContentLocalizer;
use App\Services\LookupService;
use App\Services\PageCache;
use App\Services\PageViewRecorder;
use App\Services\Queries\HeritageQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public const FILTERS = ['q', 'province', 'type', 'level', 'period', 'tag', 'sort'];

    public function index(Request $request, LookupService $lookup, PageCache $cache): Response
    {
        $filters = $this->filters($request, self::FILTERS);
        $filters['sort'] = in_array($filters['sort'], HeritageQuery::SITE_SORTS, true) ? $filters['sort'] : null;
        $unfiltered = array_filter($filters, fn ($v) => $v !== null && $v !== '') === [];

        // Danh sách + điểm bản đồ chỉ đệm khi không lọc (khóa theo trang); danh mục lọc luôn đệm
        // Bản đệm không mang query lạ (vd ?utm=...) vào liên kết phân trang dùng chung
        $listing = fn () => [
            'sites' => SiteCardResource::paginate(tap(
                HeritageQuery::sites($filters)->with(SiteCardResource::eager())->paginate(12),
                fn ($page) => $unfiltered ? $page : $page->withQueryString(),
            )),
            'mapPoints' => MapPoints::all(HeritageQuery::sites($filters)),
        ];
        $data = $unfiltered
            ? $cache->remember('sites.index.p'.max(1, (int) $request->query('page', 1)), $listing)
            : $listing();

        return Inertia::render('Sites/Index', $data + $cache->remember('sites.lookups', fn () => [
            'provinces' => $lookup->provinces(true),
            'periods' => $lookup->periods(),
            'tags' => $lookup->tags('chu_de'),
        ]) + [
            'filters' => $filters,
            'types' => $this->enumOptions('site_types', HeritageSite::TYPES),
            'levels' => $this->enumOptions('levels', HeritageSite::LEVELS),
        ]);
    }

    public function show(Request $request, HeritageSite $site, ContentLocalizer $content, PageViewRecorder $views, PageCache $cache): Response
    {
        abort_unless($site->status === 'published', 404);
        $depth = $content->normalizeDepth($request->query('depth', 1));
        $views->record($site, $request);

        // Chi tiết + liên quan không phụ thuộc người dùng → đệm theo (di sản, độ sâu, locale)
        $data = $cache->remember("sites.show.{$site->id}.d{$depth}", fn () => [
            'site' => SiteDetailResource::detail($site->load(SiteDetailResource::eager()), $depth),
            'related' => SiteCardResource::list($this->related($site)),
        ]);

        return Inertia::render('Sites/Show', $data + [
            'depth' => $depth,
            'isFavorite' => FavoriteController::isFavorite($request->user(), $site),
        ]);
    }

    private function related(HeritageSite $site)
    {
        $tagIds = $site->relationLoaded('tags') ? $site->tags->pluck('id') : $site->tags()->pluck('tags.id');

        return HeritageSite::published()->whereKeyNot($site->id)
            ->where(fn ($q) => $q->where('province_id', $site->province_id)
                ->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds)))
            ->with(SiteCardResource::eager())->orderByDesc('view_count')->limit(4)->get();
    }
}
