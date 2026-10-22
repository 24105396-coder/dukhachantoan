<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyContactTranslation extends Model
{
    protected $fillable = ['contact_id', 'locale', 'name', 'note'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(EmergencyContact::class, 'contact_id');
    }
}
