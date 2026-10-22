<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaCaption extends Model
{
    public $timestamps = false;
    protected $fillable = ['media_id', 'locale', 'caption', 'transcript'];
}
