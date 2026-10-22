<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contribution extends Model
{
    public const TYPES = ['new_site', 'new_artifact', 'correction', 'story', 'media'];
    public const STATUSES = ['pending', 'approved', 'rejected', 'needs_revision'];

    protected $fillable = [
        'user_id', 'subject_type', 'subject_id', 'contribution_type', 'locale', 'title', 'body', 'attachment_path',
        'suggested_tags', 'status', 'reviewed_by', 'review_note', 'reviewed_at',
    ];

    protected $casts = ['suggested_tags' => 'array', 'reviewed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
