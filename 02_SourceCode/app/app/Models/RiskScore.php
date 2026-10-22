<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Điểm rủi ro 0..100 và mức 0..4 của vùng; Python tính mỗi 30 phút. */
class RiskScore extends Model
{
    public const LEVELS = [0, 1, 2, 3, 4];

    protected $fillable = ['zone_id', 'computed_at', 'score', 'level', 'factors', 'source'];

    protected function casts(): array
    {
        return ['computed_at' => 'datetime', 'score' => 'integer', 'level' => 'integer', 'factors' => 'array'];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
