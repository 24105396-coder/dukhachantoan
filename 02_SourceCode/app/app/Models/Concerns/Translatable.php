<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dùng cho các bảng có bảng *_translations đơn giản (period, tag, tour_route, timeline_event).
 * Lớp sử dụng phải khai báo $translationModel và $translationForeignKey.
 */
trait Translatable
{
    public function translations(): HasMany
    {
        return $this->hasMany($this->translationModel, $this->translationForeignKey);
    }

    /** Lấy bản dịch theo locale, dự phòng sang locale mặc định rồi tới bản dịch bất kỳ. */
    public function translate(?string $locale = null): ?object
    {
        $locale ??= app()->getLocale();
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', config('app.fallback_locale'))
            ?? $translations->first();
    }

    public function getNameAttribute(): string
    {
        return $this->translate()?->name ?? $this->slug ?? '';
    }

    public function scopeWithTranslation($query, ?string $locale = null)
    {
        return $query->with('translations');
    }
}
