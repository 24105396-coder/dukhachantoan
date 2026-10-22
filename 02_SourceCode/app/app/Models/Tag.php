<?php

namespace App\Models;

use App\Models\Concerns\ResolvesByIdOrKey;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    use Translatable, ResolvesByIdOrKey;

    public const GROUPS = ['chu_de', 'loai_hinh', 'ton_giao', 'phong_cach', 'vat_lieu', 'thoi_ky', 'vung_mien'];

    protected string $translationModel = TagTranslation::class;
    protected string $translationForeignKey = 'tag_id';

    protected $fillable = ['slug', 'tag_group', 'parent_id', 'usage_count'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Tag::class, 'parent_id');
    }

    public function sites(): MorphToMany
    {
        return $this->morphedByMany(HeritageSite::class, 'taggable', 'taggables');
    }

    public function artifacts(): MorphToMany
    {
        return $this->morphedByMany(Artifact::class, 'taggable', 'taggables');
    }

    public function figures(): MorphToMany
    {
        return $this->morphedByMany(HistoricalFigure::class, 'taggable', 'taggables');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
