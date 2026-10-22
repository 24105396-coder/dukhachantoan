<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    public const LICENSES = ['public_domain', 'cc_by', 'cc_by_sa', 'cc_by_nc', 'all_rights_reserved', 'simulated'];

    protected $fillable = [
        'subject_type', 'subject_id', 'media_type', 'locale', 'path', 'external_url', 'mime', 'size_bytes',
        'duration_sec', 'width', 'height', 'sort_order', 'is_cover', 'source_name', 'source_url', 'author',
        'license', 'rights_holder', 'captured_at', 'provenance_note', 'uploaded_by',
    ];

    protected $casts = ['is_cover' => 'boolean', 'captured_at' => 'date'];

    protected $appends = ['url'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function captions(): HasMany
    {
        return $this->hasMany(MediaCaption::class, 'media_id');
    }

    public function caption(?string $locale = null): ?MediaCaption
    {
        $locale ??= app()->getLocale();
        $captions = $this->relationLoaded('captions') ? $this->captions : $this->captions()->get();

        return $captions->firstWhere('locale', $locale) ?? $captions->first();
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->external_url) {
            return $this->external_url;
        }

        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }
}
