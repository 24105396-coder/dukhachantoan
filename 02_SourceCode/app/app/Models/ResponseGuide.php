<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Models\Concerns\ResolvesByIdOrKey;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hướng dẫn ứng phó đa ngữ theo loại rủi ro và mức tối thiểu (F7). */
class ResponseGuide extends Model
{
    use HasMedia, ResolvesByIdOrKey, Translatable;

    public const AUDIENCES = ['all', 'international', 'elderly', 'family'];
    public const STATUSES = ['draft', 'published'];

    protected string $translationModel = ResponseGuideTranslation::class;
    protected string $translationForeignKey = 'guide_id';

    protected $fillable = ['slug', 'hazard_type_id', 'min_severity', 'audience', 'sort_order', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['min_severity' => 'integer', 'sort_order' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function hazardType(): BelongsTo
    {
        return $this->belongsTo(HazardType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
