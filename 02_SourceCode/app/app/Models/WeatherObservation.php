<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Quan trắc hiện tại theo vùng (Open-Meteo, do dịch vụ Python ghi). */
class WeatherObservation extends Model
{
    protected $fillable = [
        'zone_id', 'observed_at', 'temp_c', 'apparent_c', 'humidity', 'wind_kph', 'gust_kph', 'wind_dir', 'rain_mm',
        'weather_code', 'source', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'fetched_at' => 'datetime',
            'temp_c' => 'float',
            'apparent_c' => 'float',
            'humidity' => 'integer',
            'wind_kph' => 'float',
            'gust_kph' => 'float',
            'wind_dir' => 'integer',
            'rain_mm' => 'float',
            'weather_code' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
