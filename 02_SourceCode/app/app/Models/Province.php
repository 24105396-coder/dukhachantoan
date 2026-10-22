<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = ['code', 'name', 'name_en', 'region', 'lat', 'lng'];

    protected $casts = ['lat' => 'float', 'lng' => 'float'];

    public function sites(): HasMany
    {
        return $this->hasMany(HeritageSite::class);
    }

    public function localizedName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'en' ? $this->name_en : $this->name;
    }
}
