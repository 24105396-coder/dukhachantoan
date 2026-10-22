<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimelineEvent extends Model
{
    use Translatable;

    protected string $translationModel = TimelineEventTranslation::class;
    protected string $translationForeignKey = 'event_id';

    protected $fillable = ['site_id', 'figure_id', 'period_id', 'event_type', 'event_year', 'event_month', 'event_day', 'end_year', 'importance'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(HeritageSite::class, 'site_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function figure(): BelongsTo
    {
        return $this->belongsTo(HistoricalFigure::class, 'figure_id');
    }

    public function getTitleAttribute(): string
    {
        return $this->translate()?->title ?? '';
    }
}
