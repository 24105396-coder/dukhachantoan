<?php

namespace App\Models\Concerns;

use App\Models\Interpretation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Thực thể có lớp diễn giải đa ngữ, đa mức chuyên sâu (di sản, hiện vật, nhân vật).
 */
trait HasInterpretations
{
    public function interpretations(): MorphMany
    {
        return $this->morphMany(Interpretation::class, 'subject');
    }

    /**
     * Lấy diễn giải theo locale và mức chuyên sâu, dự phòng: cùng locale mức 1 -> fallback locale.
     */
    public function interpretation(?string $locale = null, int $depth = 1): ?Interpretation
    {
        $locale ??= app()->getLocale();
        $items = $this->relationLoaded('interpretations') ? $this->interpretations : $this->interpretations()->get();
        $fallback = config('app.fallback_locale');

        return $items->first(fn ($i) => $i->locale === $locale && $i->depth_level === $depth)
            ?? $items->first(fn ($i) => $i->locale === $locale && $i->depth_level === 1)
            ?? $items->first(fn ($i) => $i->locale === $fallback && $i->depth_level === $depth)
            ?? $items->first(fn ($i) => $i->locale === $fallback && $i->depth_level === 1)
            ?? $items->first();
    }

    public function getTitleAttribute(): string
    {
        return $this->interpretation()?->title ?? $this->slug;
    }

    public function getSummaryAttribute(): ?string
    {
        return $this->interpretation()?->summary;
    }

    /** Các mức chuyên sâu có sẵn cho locale hiện tại. */
    public function availableDepths(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $items = $this->relationLoaded('interpretations') ? $this->interpretations : $this->interpretations()->get();

        return $items->where('locale', $locale)->pluck('depth_level')->sort()->values()->all();
    }
}
