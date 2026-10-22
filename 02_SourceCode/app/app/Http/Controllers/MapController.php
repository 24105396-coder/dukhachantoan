<?php

namespace App\Http\Controllers;

use App\Models\HeritageSite;
use App\Services\GeoJsonBuilder;
use App\Services\LookupService;
use App\Services\Queries\HeritageQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function index(Request $request, GeoJsonBuilder $geo, LookupService $lookup): Response
    {
        $filters = $this->filters($request, ['type', 'level', 'province']);
        $sites = HeritageQuery::sites($filters)->with([
            'interpretations' => fn ($q) => $q->where('depth_level', 1), 'province', 'media',
        ])->get();

        return Inertia::render('Map/Index', [
            'geojson' => $geo->sites($sites),
            'provinces' => $lookup->provinces(true),
            'types' => $this->enumOptions('site_types', HeritageSite::TYPES),
            'levels' => $this->enumOptions('levels', HeritageSite::LEVELS),
            'filters' => $filters,
        ]);
    }
}
