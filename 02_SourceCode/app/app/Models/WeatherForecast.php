<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dự báo theo ngày (7 ngày) cho một vùng. */
class WeatherForecast extends Model
{
    protected $fillable = [
        'zone_id', 'forecast_date', 'tmin_c', 'tmax_c', 'rain_mm', 'rain_prob', 'wind_max_kph', 'gust_max_kph',
        'weather_code', 'source', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'fetched_at' => 'datetime',
            'tmin_c' => 'float',
            'tmax_c' => 'float',
            'rain_mm' => 'float',
            'rain_prob' => 'integer',
            'wind_max_kph' => 'float',
            'gust_max_kph' => 'float',
            'weather_code' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
