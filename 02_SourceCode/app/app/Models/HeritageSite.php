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

class HeritageSite extends Model
{
    use HasFactory, SoftDeletes, HasInterpretations, HasMedia, HasTags, ResolvesByIdOrKey;

    public const TYPES = ['di_tich', 'danh_thang', 'di_san_phi_vat_the', 'bao_tang', 'lang_nghe'];
    public const LEVELS = ['unesco', 'quoc_gia_dac_biet', 'quoc_gia', 'cap_tinh', 'chua_xep_hang'];

    protected $fillable = [
        'slug', 'site_type', 'period_id', 'province_id', 'address', 'lat', 'lng', 'recognition_level',
        'year_recognized', 'year_built', 'visiting_hours', 'ticket_price_vnd', 'website', 'cover_media_id',
        'status', 'created_by',
    ];

    protected $casts = ['lat' => 'float', 'lng' => 'float', 'ticket_price_vnd' => 'integer'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class, 'site_id');
    }

    public function figures(): BelongsToMany
    {
        return $this->belongsToMany(HistoricalFigure::class, 'site_figure', 'site_id', 'figure_id')->withPivot('relation_type');
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(TimelineEvent::class, 'site_id')->orderBy('event_year');
    }

    public function routeStops(): HasMany
    {
        return $this->hasMany(TourRouteStop::class, 'site_id');
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
