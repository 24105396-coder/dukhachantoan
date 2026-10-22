<?php

namespace App\Http\Resources\Concerns;

use App\Http\Resources\MediaItemResource;
use App\Models\Period;
use App\Models\Province;
use Illuminate\Database\Eloquent\Model;

/** Các mảnh dữ liệu dùng chung giữa SiteCard, ArtifactCard, FigureCard… */
trait FormatsHeritage
{
    protected function coverOf(Model $model): ?array
    {
        $cover = method_exists($model, 'coverImage') ? $model->coverImage() : null;
        if (! $cover) {
            return null;
        }

        return [
            'url' => $cover->url,
            'caption' => $cover->caption()?->caption,
            'license' => $cover->license,
            'license_label' => __('enums.licenses.'.$cover->license),
            'source_name' => $cover->source_name,
            'source_url' => $cover->source_url,
            'author' => $cover->author,
        ];
    }

    protected function tagsOf(Model $model): array
    {
        if (! $model->relationLoaded('tags')) {
            return [];
        }

        return $model->tags->map(fn ($tag) => [
            'slug' => $tag->slug,
            'name' => $tag->name,
            'group' => $tag->tag_group,
        ])->values()->all();
    }

    protected function provinceOf(?Province $province): ?array
    {
        return $province ? ['code' => $province->code, 'name' => $province->localizedName()] : null;
    }

    protected function periodOf(?Period $period): ?array
    {
        return $period ? ['code' => $period->code, 'name' => $period->name] : null;
    }

    /** media: { images: [MediaItem], audios: [AudioItem], models: [MediaItem] } */
    protected function mediaGroups(Model $model): array
    {
        $media = $model->relationLoaded('media') ? $model->media : collect();
        $locale = app()->getLocale();

        return [
            'images' => MediaItemResource::list($media->where('media_type', 'image')),
            'audios' => MediaItemResource::list($media->where('media_type', 'audio')
                ->sortBy(fn ($m) => $m->locale === $locale ? 0 : 1)),
            'models' => MediaItemResource::list($media->where('media_type', 'model3d')),
        ];
    }
}
