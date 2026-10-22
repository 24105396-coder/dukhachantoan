<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = ['code', 'name', 'name_en', 'region', 'lat', 'lng'];

    protected $casts = ['lat' => 'float', 'lng' => 'float'];

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(Destination::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function localizedName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'en' ? ($this->name_en ?: $this->name) : $this->name;
    }

    /** {code, name} dùng trong Resource. */
    public function brief(): array
    {
        return ['code' => $this->code, 'name' => $this->localizedName()];
    }
}
