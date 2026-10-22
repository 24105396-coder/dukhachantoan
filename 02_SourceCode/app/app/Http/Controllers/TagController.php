<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArtifactCardResource;
use App\Http\Resources\FigureCardResource;
use App\Http\Resources\RouteCardResource;
use App\Http\Resources\SiteCardResource;
use App\Http\Resources\TagItemResource;
use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\Tag;
use App\Models\TourRoute;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        $tags = Tag::query()->with('translations')->orderByDesc('usage_count')->orderBy('slug')->get();

        $groups = collect(Tag::GROUPS)->map(fn ($group) => [
            'group' => $group,
            'label' => __('enums.tag_groups.'.$group),
            'tags' => TagItemResource::list($tags->where('tag_group', $group)),
        ])->filter(fn ($g) => count($g['tags']) > 0)->values()->all();

        return Inertia::render('Tags/Index', ['groups' => $groups]);
    }

    public function show(Tag $tag): Response
    {
        $tag->load('translations');

        return Inertia::render('Tags/Show', [
            'tag' => TagItemResource::one($tag),
            'sites' => SiteCardResource::list(HeritageSite::published()->withTag($tag->slug)->with(SiteCardResource::eager())->limit(24)->get()),
            'artifacts' => ArtifactCardResource::list(Artifact::published()->withTag($tag->slug)->with(ArtifactCardResource::eager())->limit(24)->get()),
            'figures' => FigureCardResource::list(HistoricalFigure::published()->withTag($tag->slug)->with(FigureCardResource::eager())->limit(24)->get()),
            'routes' => RouteCardResource::list(TourRoute::published()->withCount('stops')
                ->where(fn ($q) => $q->where('theme_tag_id', $tag->id)->orWhereHas('tags', fn ($t) => $t->where('tags.id', $tag->id)))
                ->with(RouteCardResource::eager())->limit(12)->get()),
        ]);
    }
}
