<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinationTranslation extends Model
{
    protected $fillable = ['destination_id', 'locale', 'name', 'description', 'safety_note'];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
