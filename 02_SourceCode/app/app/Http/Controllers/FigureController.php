<?php

namespace App\Http\Controllers;

use App\Http\Resources\FigureCardResource;
use App\Http\Resources\FigureDetailResource;
use App\Models\HistoricalFigure;
use App\Services\ContentLocalizer;
use App\Services\LookupService;
use App\Services\PageViewRecorder;
use App\Services\Queries\HeritageQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FigureController extends Controller
{
    public const FILTERS = ['q', 'type', 'period', 'tag'];

    public function index(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, self::FILTERS);
        $figures = HeritageQuery::figures($filters)->with(FigureCardResource::eager())->paginate(12)->withQueryString();

        return Inertia::render('Figures/Index', [
            'figures' => FigureCardResource::paginate($figures),
            'filters' => $filters,
            'types' => $this->enumOptions('figure_types', HistoricalFigure::TYPES),
            'periods' => $lookup->periods(),
        ]);
    }

    public function show(Request $request, HistoricalFigure $figure, ContentLocalizer $content, PageViewRecorder $views): Response
    {
        abort_unless($figure->status === 'published', 404);
        $depth = $content->normalizeDepth($request->query('depth', 1));

        $figure->load(FigureDetailResource::eager());
        $views->record($figure, $request);

        return Inertia::render('Figures/Show', [
            'figure' => FigureDetailResource::detail($figure, $depth),
            'depth' => $depth,
            'isFavorite' => FavoriteController::isFavorite($request->user(), $figure),
        ]);
    }
}
