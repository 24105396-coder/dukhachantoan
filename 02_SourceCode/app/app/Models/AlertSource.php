<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nguồn dữ liệu cảnh báo: Open-Meteo, cơ quan nhập tay, mô phỏng. */
class AlertSource extends Model
{
    public const TYPES = ['weather', 'disaster', 'security', 'health', 'traffic', 'manual', 'simulation'];
    public const PROVIDERS = ['openmeteo', 'manual', 'simulated'];

    protected $fillable = [
        'code', 'name', 'source_type', 'provider', 'base_url', 'attribution', 'license', 'poll_interval_min',
        'last_polled_at', 'last_status', 'last_error', 'is_active',
    ];

    protected function casts(): array
    {
        return ['last_polled_at' => 'datetime', 'poll_interval_min' => 'integer', 'is_active' => 'boolean'];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'source_id');
    }
}
