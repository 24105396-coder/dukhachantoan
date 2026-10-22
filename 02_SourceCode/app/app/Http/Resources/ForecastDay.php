<?php

namespace App\Http\Resources;

use App\Models\WeatherForecast;
use App\Support\WeatherCode;
use Illuminate\Http\Request;

/**
 * @mixin WeatherForecast
 */
class ForecastDay extends BaseResource
{
    public function toArray(Request $request): array
    {
        $f = $this->resource;

        return [
            'date' => $f->forecast_date?->toDateString(),
            'tmin_c' => $f->tmin_c,
            'tmax_c' => $f->tmax_c,
            'rain_mm' => $f->rain_mm,
            'rain_prob' => $f->rain_prob,
            'wind_max_kph' => $f->wind_max_kph,
            'gust_max_kph' => $f->gust_max_kph,
            'weather_code' => $f->weather_code,
            'weather_label' => WeatherCode::label($f->weather_code),
            'icon' => WeatherCode::icon($f->weather_code),
        ];
    }
}
