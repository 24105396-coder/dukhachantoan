<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    public $timestamps = false;
    protected $fillable = ['subject_type', 'subject_id', 'user_id', 'locale', 'viewed_on', 'created_at', 'counted_at'];
    protected $casts = ['viewed_on' => 'date', 'created_at' => 'datetime', 'counted_at' => 'datetime'];
}
