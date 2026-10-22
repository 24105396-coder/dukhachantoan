<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'taggables');
    }

    public function scopeWithTag($query, string $tagSlug)
    {
        return $query->whereHas('tags', fn ($q) => $q->where('slug', $tagSlug));
    }
}
