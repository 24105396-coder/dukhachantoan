<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Interpretation extends Model
{
    use HasFactory;

    public const DEPTH_POPULAR = 1;
    public const DEPTH_ADVANCED = 2;
    public const DEPTH_ACADEMIC = 3;

    protected $fillable = [
        'subject_type', 'subject_id', 'locale', 'depth_level', 'title', 'summary', 'body', 'sources',
        'is_machine_translated', 'version', 'author_id', 'reviewed_by',
    ];

    protected $casts = ['depth_level' => 'integer', 'is_machine_translated' => 'boolean', 'version' => 'integer'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
