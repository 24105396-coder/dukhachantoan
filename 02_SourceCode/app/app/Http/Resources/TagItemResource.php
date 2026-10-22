<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TagItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $translation = $this->translate();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $translation?->name ?? $this->slug,
            'description' => $translation?->description,
            'group' => $this->tag_group,
            'usage_count' => (int) $this->usage_count,
            'url' => localized_route('tags.show', ['tag' => $this->slug]),
        ];
    }
}
