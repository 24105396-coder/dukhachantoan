<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Danh bạ khẩn cấp theo tỉnh/vùng (F6), có nguồn và ngày xác minh. province_id null = toàn quốc. */
class EmergencyContact extends Model
{
    use Translatable;

    public const TYPES = ['police', 'fire', 'ambulance', 'rescue', 'coast_guard', 'tourist_support', 'hospital', 'embassy', 'hotline_national'];

    protected string $translationModel = EmergencyContactTranslation::class;
    protected string $translationForeignKey = 'contact_id';

    protected $fillable = [
        'province_id', 'zone_id', 'contact_type', 'phone', 'phone_alt', 'address', 'lat', 'lng', 'hours', 'languages',
        'is_verified', 'verified_at', 'source_name', 'source_url', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'languages' => 'array',
            'is_verified' => 'boolean',
            'verified_at' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
