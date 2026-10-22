<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\SiteCardResource;
use App\Http\Resources\SiteDetailResource;
use App\Models\HeritageSite;
use App\Services\ContentLocalizer;
use App\Services\Queries\HeritageQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->applyLocale($request);
        $filters = $this->filters($request, ['q', 'province', 'type', 'level', 'period', 'tag', 'sort']);
        $page = HeritageQuery::sites($filters)->with(SiteCardResource::eager())->paginate($this->perPage($request))->withQueryString();

        return $this->paginated($page, SiteCardResource::class);
    }

    public function show(Request $request, string $slug, ContentLocalizer $content): JsonResponse
    {
        $this->applyLocale($request);
        $site = HeritageSite::published()->where('slug', $slug)->with(SiteDetailResource::eager())->firstOrFail();

        return response()->json(['data' => SiteDetailResource::detail($site, $content->normalizeDepth($request->query('depth', 1)))]);
    }
}
