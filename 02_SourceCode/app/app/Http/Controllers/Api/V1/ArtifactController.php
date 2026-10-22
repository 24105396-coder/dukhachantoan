<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtifactCardResource;
use App\Http\Resources\ArtifactDetailResource;
use App\Models\Artifact;
use App\Services\ContentLocalizer;
use App\Services\Queries\HeritageQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtifactController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->applyLocale($request);
        $filters = $this->filters($request, ['q', 'type', 'site', 'treasure', 'period', 'tag']);
        $page = HeritageQuery::artifacts($filters)->with(ArtifactCardResource::eager())->paginate($this->perPage($request))->withQueryString();

        return $this->paginated($page, ArtifactCardResource::class);
    }

    public function show(Request $request, string $slug, ContentLocalizer $content): JsonResponse
    {
        $this->applyLocale($request);
        $artifact = Artifact::published()->where('slug', $slug)->with(ArtifactDetailResource::eager())->firstOrFail();

        return response()->json(['data' => ArtifactDetailResource::detail($artifact, $content->normalizeDepth($request->query('depth', 1)))]);
    }
}
