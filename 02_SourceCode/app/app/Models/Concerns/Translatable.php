<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dùng cho bảng có bảng *_translations (destination, hazard_type, alert, contact, guide).
 * Lớp sử dụng khai báo $translationModel và $translationForeignKey.
 */
trait Translatable
{
    public function translations(): HasMany
    {
        return $this->hasMany($this->translationModel, $this->translationForeignKey);
    }

    /** Bản dịch theo locale, dự phòng locale mặc định rồi tới bản dịch bất kỳ. */
    public function translate(?string $locale = null): ?object
    {
        $locale ??= app()->getLocale();
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', config('app.fallback_locale'))
            ?? $translations->first();
    }

    /** Giá trị một trường đã dịch (null nếu không có bản dịch nào). */
    public function translated(string $field, ?string $locale = null): mixed
    {
        return $this->translate($locale)?->{$field};
    }

    /** Locale nào đang có bản dịch (vd ['vi','en']). */
    public function availableLocales(): array
    {
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $translations->pluck('locale')->unique()->values()->all();
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->translated('name') ?? $this->attributes['slug'] ?? $this->attributes['code'] ?? '');
    }

    public function getTitleAttribute(): string
    {
        return (string) ($this->translated('title') ?? $this->attributes['slug'] ?? $this->attributes['code'] ?? '');
    }

    /**
     * Ghi bản dịch từ mảng {locale: {field: value}}; bỏ qua locale rỗng.
     */
    public function syncTranslations(array $byLocale): void
    {
        foreach ($byLocale as $locale => $fields) {
            if (! in_array($locale, ['vi', 'en'], true) || ! is_array($fields) || $fields === []) {
                continue;
            }
            $this->translations()->updateOrCreate(['locale' => $locale], $fields);
        }
        $this->unsetRelation('translations');
    }

    public function scopeWithTranslation($query)
    {
        return $query->with('translations');
    }
}
