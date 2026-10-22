<?php

namespace App\Models\Concerns;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'subject')->orderBy('sort_order');
    }

    public function images(): MorphMany
    {
        return $this->media()->where('media_type', 'image');
    }

    public function audios(): MorphMany
    {
        return $this->media()->where('media_type', 'audio');
    }

    public function coverImage(): ?MediaAsset
    {
        $media = $this->relationLoaded('media') ? $this->media : $this->media()->get();

        return $media->firstWhere('is_cover', true) ?? $media->firstWhere('media_type', 'image');
    }
}
