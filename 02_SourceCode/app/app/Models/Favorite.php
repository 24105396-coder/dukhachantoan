<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Favorite extends Model
{
    protected $fillable = ['user_id', 'subject_type', 'subject_id'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
