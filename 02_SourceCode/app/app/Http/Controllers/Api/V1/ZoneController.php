<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ForecastDay;
use App\Http\Resources\WeatherNow;
use App\Http\Resources\ZoneCard;
use App\Http\Resources\ZoneGeo;
use App\Models\Zone;
use App\Services\RiskService;
use App\Services\WeatherService;
use App\Support\RiskFactors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API vùng: danh sách ZoneCard, GeoJSON vùng tô màu theo mức, thời tiết + rủi ro của một vùng. */
class ZoneController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->applyLocale($request);

        return response()->json(['data' => ZoneCard::list(Zone::query()->active()->with('province')->orderBy('id')->get())]);
    }

    public function geojson(Request $request): JsonResponse
    {
        $this->applyLocale($request);

        return response()->json(ZoneGeo::collection(Zone::query()->active()->orderBy('id')->get()))
            ->header('Content-Type', 'application/geo+json');
    }

    public function weather(Request $request, WeatherService $weather, RiskService $risk, string $zone): JsonResponse
    {
        $this->applyLocale($request);
        $model = Zone::query()->where('code', $zone)->firstOrFail();
        $stats = $risk->zoneStats()[$model->id] ?? null;

        return response()->json(['data' => [
            'zone' => $model->brief(),
            'now' => WeatherNow::one($weather->now($model)),
            'forecast' => ForecastDay::list($weather->forecast($model)),
            'risk' => $stats ? ['score' => $stats['score'], 'level' => $stats['level'], 'factors' => RiskFactors::present($stats['factors']), 'computed_at' => $stats['computed_at']] : null,
        ]]);
    }
}
