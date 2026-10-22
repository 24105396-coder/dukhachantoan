<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/** Bảng nối cảnh báo – vùng ảnh hưởng. */
class AlertZone extends Pivot
{
    protected $table = 'alert_zones';

    public $incrementing = false;

    protected $fillable = ['alert_id', 'zone_id'];
}
