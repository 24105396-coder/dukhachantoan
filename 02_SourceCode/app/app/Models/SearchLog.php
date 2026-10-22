<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'locale', 'query', 'result_count', 'engine', 'duration_ms', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
