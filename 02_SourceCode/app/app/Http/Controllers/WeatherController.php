<?php

namespace App\Http\Controllers;

use App\Http\Resources\ForecastDay;
use App\Http\Resources\WeatherNow;
use App\Http\Resources\ZoneCard;
use App\Models\Zone;
use App\Services\RiskService;
use App\Services\WeatherService;
use Inertia\Inertia;
use Inertia\Response;

/** Thời tiết hiện tại + dự báo 7 ngày + điểm rủi ro của một vùng (mặc định Hà Nội). */
class WeatherController extends Controller
{
    public function __invoke(WeatherService $weather, RiskService $risk, ?string $zone = null): Response
    {
        $code = $zone ?? config('dukhach.weather.default_zone');
        $model = Zone::query()->active()->with('province')->where('code', $code)->first()
            ?? ($zone === null ? Zone::query()->active()->with('province')->orderBy('id')->first() : null);
        abort_if($model === null, 404);
        $stats = $risk->zoneStats()[$model->id] ?? null;

        return Inertia::render('Weather/Show', [
            'zone' => ZoneCard::one($model),
            'now' => WeatherNow::one($weather->now($model)),
            'forecast' => ForecastDay::list($weather->forecast($model)),
            'risk' => $stats ? ['score' => $stats['score'], 'level' => $stats['level'], 'factors' => $stats['factors'], 'computed_at' => $stats['computed_at']] : null,
            'zones' => Zone::query()->active()->orderBy('name')->get()->map(fn (Zone $z) => $z->brief() + ['zone_type' => $z->zone_type])->all(),
        ]);
    }
}
