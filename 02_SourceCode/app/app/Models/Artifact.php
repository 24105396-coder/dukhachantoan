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
use Illuminate\Database\Eloquent\SoftDeletes;

class Artifact extends Model
{
    use HasFactory, SoftDeletes, HasInterpretations, HasMedia, HasTags, ResolvesByIdOrKey;

    public const TYPES = ['tuong', 'bia_ky', 'do_gom', 'do_dong', 'vu_khi', 'trang_phuc', 'kien_truc', 'tai_lieu', 'bao_vat', 'khac'];

    protected $fillable = [
        'site_id', 'slug', 'inventory_code', 'artifact_type', 'material', 'period_id', 'year_from', 'year_to',
        'height_cm', 'width_cm', 'depth_cm', 'weight_kg', 'is_national_treasure', 'model3d_url', 'cover_media_id',
        'status', 'created_by',
    ];

    protected $casts = ['is_national_treasure' => 'boolean', 'height_cm' => 'float', 'width_cm' => 'float', 'depth_cm' => 'float', 'weight_kg' => 'float'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(HeritageSite::class, 'site_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
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
