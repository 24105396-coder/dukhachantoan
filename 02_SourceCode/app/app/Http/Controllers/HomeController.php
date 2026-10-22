<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertCard;
use App\Http\Resources\GuideCard;
use App\Http\Resources\WeatherNow;
use App\Http\Resources\ZoneGeo;
use App\Models\Alert;
use App\Models\ResponseGuide;
use App\Models\Zone;
use App\Services\LookupService;
use App\Services\PageCache;
use App\Services\RiskService;
use App\Services\WeatherService;
use Inertia\Inertia;
use Inertia\Response;

/** Trang chủ: mức rủi ro toàn quốc, bản đồ vùng, cảnh báo nổi bật, thời tiết 5 vùng du lịch, hướng dẫn. */
class HomeController extends Controller
{
    public const TOP_ALERTS = 6;
    public const GUIDES = 4;

    public function __invoke(LookupService $lookup, RiskService $risk, WeatherService $weather, PageCache $cache): Response
    {
        $summary = $risk->nationalSummary();

        return Inertia::render('Home', [
            'national' => [
                'level' => $summary['national_level'],
                'label' => RiskService::levelLabel($summary['national_level']),
                'updated_at' => $summary['updated_at'],
            ],
            'zonesGeo' => $cache->remember('zones-geo', fn () => ZoneGeo::collection(Zone::query()->active()->orderBy('id')->get())),
            'topAlerts' => AlertCard::list(Alert::query()->live()->with(AlertCard::RELATIONS)->mostSevere()->limit(self::TOP_ALERTS)->get()),
            'weatherHighlights' => WeatherNow::list($weather->highlights()),
            'guides' => $cache->remember('home-guides', fn () => GuideCard::list(ResponseGuide::query()->published()
                ->with(GuideCard::RELATIONS)->orderBy('sort_order')->orderBy('id')->limit(self::GUIDES)->get())),
            'stats' => $lookup->stats(),
        ]);
    }
}
