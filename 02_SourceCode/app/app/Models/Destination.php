<?php

namespace App\Models;

use App\Models\Concerns\HasGeometry;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Điểm đến du lịch thuộc một vùng; tên/mô tả/lưu ý an toàn đa ngữ. */
class Destination extends Model
{
    use HasGeometry, HasMedia, Translatable;

    public const TYPES = ['bai_bien', 'nui', 'dao', 'do_thi', 'di_tich', 'vuon_quoc_gia', 'cua_khau', 'san_bay', 'ben_cang', 'khac'];

    protected string $translationModel = DestinationTranslation::class;
    protected string $translationForeignKey = 'destination_id';

    protected $fillable = ['slug', 'zone_id', 'province_id', 'dest_type', 'lat', 'lng', 'altitude_m', 'is_popular'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'altitude_m' => 'integer', 'is_popular' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function legs(): HasMany
    {
        return $this->hasMany(TripLeg::class);
    }
}
