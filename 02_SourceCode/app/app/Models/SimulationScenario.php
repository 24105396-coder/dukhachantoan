<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kịch bản mô phỏng (bão đổ bộ, lũ quét, nắng nóng…). Tên/mô tả cột chính là tiếng Việt;
 * bản đa ngữ và các bước nằm trong payload {name:{vi,en}, description:{vi,en}, steps:[…]}.
 */
class SimulationScenario extends Model
{
    public const STATUSES = ['ready', 'running', 'finished'];

    protected $fillable = [
        'slug', 'name', 'description', 'hazard_type_id', 'severity', 'zone_codes', 'duration_hours', 'payload', 'created_by', 'status',
    ];

    protected function casts(): array
    {
        return ['severity' => 'integer', 'zone_codes' => 'array', 'duration_hours' => 'integer', 'payload' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function hazardType(): BelongsTo
    {
        return $this->belongsTo(HazardType::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SimulationRun::class, 'scenario_id');
    }

    public function lastRun(): HasOne
    {
        return $this->hasOne(SimulationRun::class, 'scenario_id')->latestOfMany('started_at');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'scenario_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Tên theo locale từ payload, dự phòng cột name. */
    public function localized(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $value = $this->payload[$field] ?? null;

        return is_array($value) ? ($value[$locale] ?? $value['vi'] ?? $this->{$field}) : $this->{$field};
    }
}
