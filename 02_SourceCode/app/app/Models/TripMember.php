<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Thành viên đoàn (dữ liệu mô phỏng, không PII thật). */
class TripMember extends Model
{
    public const AGE_GROUPS = ['child', 'adult', 'senior'];

    protected $fillable = ['trip_id', 'full_name', 'phone', 'nationality', 'age_group', 'special_needs', 'is_leader'];

    protected function casts(): array
    {
        return ['is_leader' => 'boolean'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** Che 4 số giữa: 0912345678 → 091****678. */
    public function getPhoneMaskedAttribute(): ?string
    {
        return self::maskPhone($this->phone);
    }

    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $length = mb_strlen($phone);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }
        $start = intdiv($length - 4, 2);

        return mb_substr($phone, 0, $start).'****'.mb_substr($phone, $start + 4);
    }
}
