<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimelineEventTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['event_id', 'locale', 'title', 'description'];
}
