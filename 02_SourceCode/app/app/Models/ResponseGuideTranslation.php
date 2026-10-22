<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponseGuideTranslation extends Model
{
    protected $fillable = [
        'guide_id', 'locale', 'title', 'summary', 'before_steps', 'during_steps', 'after_steps', 'sources', 'is_machine_translated',
    ];

    protected function casts(): array
    {
        return [
            'before_steps' => 'array',
            'during_steps' => 'array',
            'after_steps' => 'array',
            'sources' => 'array',
            'is_machine_translated' => 'boolean',
        ];
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(ResponseGuide::class, 'guide_id');
    }
}
