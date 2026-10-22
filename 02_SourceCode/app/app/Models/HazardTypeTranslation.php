<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HazardTypeTranslation extends Model
{
    protected $fillable = ['hazard_type_id', 'locale', 'name', 'description'];

    public function hazardType(): BelongsTo
    {
        return $this->belongsTo(HazardType::class);
    }
}
