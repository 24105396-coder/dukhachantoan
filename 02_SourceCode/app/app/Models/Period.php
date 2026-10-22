<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    use Translatable;

    protected string $translationModel = PeriodTranslation::class;
    protected string $translationForeignKey = 'period_id';

    protected $fillable = ['code', 'start_year', 'end_year', 'sort_order'];
}
