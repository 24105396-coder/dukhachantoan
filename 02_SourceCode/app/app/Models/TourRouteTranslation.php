<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourRouteTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['route_id', 'locale', 'name', 'description', 'highlights'];
}
