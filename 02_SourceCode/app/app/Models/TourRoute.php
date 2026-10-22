<?php

namespace App\Models;

use App\Models\Concerns\ResolvesByIdOrKey;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TourRoute extends Model
{
    use HasFactory, SoftDeletes, Translatable, HasMedia, HasTags, ResolvesByIdOrKey;

    protected string $translationModel = TourRouteTranslation::class;
    protected string $translationForeignKey = 'route_id';

    protected $fillable = [
        'slug', 'province_id', 'theme_tag_id', 'duration_minutes', 'distance_km', 'difficulty', 'price_vnd',
        'default_capacity', 'cover_media_id', 'status', 'created_by',
    ];

    protected $casts = ['distance_km' => 'float', 'price_vnd' => 'integer'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function themeTag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'theme_tag_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TourRouteStop::class, 'route_id')->orderBy('stop_order');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(TourSlot::class, 'route_id')->orderBy('starts_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
