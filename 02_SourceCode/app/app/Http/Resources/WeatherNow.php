<?php

namespace App\Http\Resources;

use App\Models\WeatherObservation;
use App\Support\WeatherCode;
use Illuminate\Http\Request;

/**
 * @mixin WeatherObservation
 */
class WeatherNow extends BaseResource
{
    public function toArray(Request $request): array
    {
        $o = $this->resource;

        return [
            'zone' => $o->zone?->brief(),
            'observed_at' => self::iso($o->observed_at),
            'temp_c' => $o->temp_c,
            'apparent_c' => $o->apparent_c,
            'humidity' => $o->humidity,
            'wind_kph' => $o->wind_kph,
            'gust_kph' => $o->gust_kph,
            'wind_dir' => $o->wind_dir,
            'rain_mm' => $o->rain_mm,
            'weather_code' => $o->weather_code,
            'weather_label' => WeatherCode::label($o->weather_code),
            'icon' => WeatherCode::icon($o->weather_code),
            'source' => 'Open-Meteo',
            'fetched_at' => self::iso($o->fetched_at),
        ];
    }
}
