<?php

namespace App\Http\Controllers;

use App\Services\GeoJsonBuilder;
use App\Services\LookupService;
use App\Services\RiskService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trang chủ. Pha 1: đủ khóa props theo page-contract, dữ liệu rỗng an toàn;
 * pha 2 điền zonesGeo/topAlerts/weatherHighlights/guides từ Resource.
 */
class HomeController extends Controller
{
    public function __invoke(LookupService $lookup, RiskService $risk): Response
    {
        $summary = $risk->nationalSummary();

        return Inertia::render('Home', [
            'national' => [
                'level' => $summary['national_level'],
                'label' => RiskService::levelLabel($summary['national_level']),
                'updated_at' => $summary['updated_at'],
            ],
            'zonesGeo' => GeoJsonBuilder::empty(),
            'topAlerts' => [],
            'weatherHighlights' => [],
            'guides' => [],
            'stats' => $lookup->stats(),
        ]);
    }
}
