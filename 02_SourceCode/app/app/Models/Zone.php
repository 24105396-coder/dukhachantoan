<?php

namespace App\Models;

use App\Models\Concerns\HasGeometry;
use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Vùng rủi ro: tỉnh hoặc vùng đặc thù (vịnh, núi, đảo…), hình học GeoJSON hoặc tâm + bán kính. */
class Zone extends Model
{
    use HasFactory, HasGeometry, HasMedia;

    public const TYPES = ['province', 'district', 'coast', 'mountain', 'island', 'urban', 'custom'];

    protected $fillable = [
        'code', 'zone_type', 'province_id', 'parent_id', 'name', 'name_en', 'geometry', 'centroid_lat', 'centroid_lng',
        'radius_km', 'population_note', 'source_name', 'source_url', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'centroid_lat' => 'float',
            'centroid_lng' => 'float',
            'radius_km' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(Destination::class);
    }

    public function alerts(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class, 'alert_zones')->using(AlertZone::class)->withTimestamps();
    }

    public function legs(): HasMany
    {
        return $this->hasMany(TripLeg::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function weatherObservations(): HasMany
    {
        return $this->hasMany(WeatherObservation::class);
    }

    public function weatherForecasts(): HasMany
    {
        return $this->hasMany(WeatherForecast::class);
    }

    public function riskScores(): HasMany
    {
        return $this->hasMany(RiskScore::class);
    }

    public function latestRisk(): HasOne
    {
        return $this->hasOne(RiskScore::class)->latestOfMany('computed_at');
    }

    public function latestObservation(): HasOne
    {
        return $this->hasOne(WeatherObservation::class)->latestOfMany('observed_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function localizedName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'en' ? ($this->name_en ?: $this->name) : $this->name;
    }

    public function brief(): array
    {
        return ['code' => $this->code, 'name' => $this->localizedName()];
    }
}
