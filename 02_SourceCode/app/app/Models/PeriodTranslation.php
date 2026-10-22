<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['period_id', 'locale', 'name', 'description'];
}
