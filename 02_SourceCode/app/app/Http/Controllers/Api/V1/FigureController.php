<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\FigureCardResource;
use App\Http\Resources\FigureDetailResource;
use App\Models\HistoricalFigure;
use App\Services\ContentLocalizer;
use App\Services\Queries\HeritageQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FigureController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->applyLocale($request);
        $filters = $this->filters($request, ['q', 'type', 'period', 'tag']);
        $page = HeritageQuery::figures($filters)->with(FigureCardResource::eager())->paginate($this->perPage($request))->withQueryString();

        return $this->paginated($page, FigureCardResource::class);
    }

    public function show(Request $request, string $slug, ContentLocalizer $content): JsonResponse
    {
        $this->applyLocale($request);
        $figure = HistoricalFigure::published()->where('slug', $slug)->with(FigureDetailResource::eager())->firstOrFail();

        return response()->json(['data' => FigureDetailResource::detail($figure, $content->normalizeDepth($request->query('depth', 1)))]);
    }
}
