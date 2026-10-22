<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\TimelineController;
use App\Http\Resources\TagItemResource;
use App\Http\Resources\TimelineItemResource;
use App\Models\Tag;
use App\Services\GeoJsonBuilder;
use App\Services\Queries\HeritageQuery;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GeoJSON, dòng thời gian, thẻ, tìm kiếm – các điểm cuối chỉ đọc. */
class CatalogController extends ApiController
{
    public function geojson(Request $request, GeoJsonBuilder $geo): JsonResponse
    {
        $this->applyLocale($request);
        $sites = HeritageQuery::sites($this->filters($request, ['type', 'level', 'province']))
            ->with(['interpretations' => fn ($q) => $q->where('depth_level', 1), 'province', 'media'])->get();

        return response()->json($geo->sites($sites), 200, ['Content-Type' => 'application/geo+json']);
    }

    public function timeline(Request $request): JsonResponse
    {
        $this->applyLocale($request);
        $filters = $this->filters($request, ['from', 'to', 'period', 'type', 'q']);

        return response()->json(TimelineItemResource::list(TimelineController::query($filters)->get()));
    }

    public function tags(Request $request): JsonResponse
    {
        $this->applyLocale($request);
        $tags = Tag::query()->with('translations')
            ->when(in_array($request->query('group'), Tag::GROUPS, true), fn ($q) => $q->where('tag_group', $request->query('group')))
            ->orderByDesc('usage_count')->orderBy('slug')->get();

        return response()->json(TagItemResource::list($tags));
    }

    public function search(Request $request, SearchService $search): JsonResponse
    {
        $locale = $this->applyLocale($request);
        $q = $this->filters($request, ['q'])['q'] ?? '';
        $result = $search->search($q, $locale, null);

        return response()->json([
            'engine' => $result['engine'],
            'took_ms' => $result['durationMs'],
            'data' => $result['results'],
        ]);
    }
}
