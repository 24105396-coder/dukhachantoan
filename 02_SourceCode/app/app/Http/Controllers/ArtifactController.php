<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArtifactCardResource;
use App\Http\Resources\ArtifactDetailResource;
use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Services\ContentLocalizer;
use App\Services\LookupService;
use App\Services\PageViewRecorder;
use App\Services\Queries\HeritageQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArtifactController extends Controller
{
    public const FILTERS = ['q', 'type', 'site', 'treasure', 'period', 'tag'];

    public function index(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, self::FILTERS);
        $artifacts = HeritageQuery::artifacts($filters)->with(ArtifactCardResource::eager())->paginate(12)->withQueryString();

        $sites = HeritageSite::published()->whereHas('artifacts', fn ($q) => $q->published())
            ->with(['interpretations' => fn ($q) => $q->where('depth_level', 1)])->orderBy('slug')->get()
            ->map(fn ($s) => ['slug' => $s->slug, 'title' => $s->title])->all();

        return Inertia::render('Artifacts/Index', [
            'artifacts' => ArtifactCardResource::paginate($artifacts),
            'filters' => $filters,
            'types' => $this->enumOptions('artifact_types', Artifact::TYPES),
            'sites' => $sites,
            'periods' => $lookup->periods(),
        ]);
    }

    public function show(Request $request, Artifact $artifact, ContentLocalizer $content, PageViewRecorder $views): Response
    {
        abort_unless($artifact->status === 'published', 404);
        $depth = $content->normalizeDepth($request->query('depth', 1));

        $artifact->load(ArtifactDetailResource::eager());
        $views->record($artifact, $request);

        $related = Artifact::published()->whereKeyNot($artifact->id)
            ->where(fn ($q) => $q->where('site_id', $artifact->site_id)->orWhere('artifact_type', $artifact->artifact_type))
            ->with(ArtifactCardResource::eager())->orderByRaw('site_id = ? DESC', [$artifact->site_id])->limit(4)->get();

        return Inertia::render('Artifacts/Show', [
            'artifact' => ArtifactDetailResource::detail($artifact, $depth),
            'depth' => $depth,
            'related' => ArtifactCardResource::list($related),
            'isFavorite' => FavoriteController::isFavorite($request->user(), $artifact),
        ]);
    }
}
