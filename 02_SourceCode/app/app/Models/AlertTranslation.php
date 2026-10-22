<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertTranslation extends Model
{
    protected $fillable = ['alert_id', 'locale', 'headline', 'description', 'instructions', 'is_machine_translated'];

    protected function casts(): array
    {
        return ['is_machine_translated' => 'boolean'];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }
}
