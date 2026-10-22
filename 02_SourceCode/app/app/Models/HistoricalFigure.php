<?php

namespace App\Models;

use App\Models\Concerns\ResolvesByIdOrKey;
use App\Models\Concerns\HasInterpretations;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HistoricalFigure extends Model
{
    use HasFactory, SoftDeletes, HasInterpretations, HasMedia, HasTags, ResolvesByIdOrKey;

    public const TYPES = ['vua_chua', 'tuong_linh', 'nha_van_hoa', 'anh_hung', 'nhan_vat_ton_giao', 'nghe_nhan', 'khac'];

    protected $fillable = ['slug', 'figure_type', 'birth_year', 'death_year', 'period_id', 'province_id', 'portrait_media_id', 'status', 'created_by'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(HeritageSite::class, 'site_figure', 'figure_id', 'site_id')->withPivot('relation_type');
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(TimelineEvent::class, 'figure_id')->orderBy('event_year');
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
