<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\SiteRequest;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SiteController extends HeritageAdminController
{
    protected function modelClass(): string { return HeritageSite::class; }

    protected function pageFolder(): string { return 'Sites'; }

    protected function routeKey(): string { return 'sites'; }

    protected function propName(): string { return 'site'; }

    protected function columns(): array
    {
        return ['slug', 'site_type', 'period_id', 'province_id', 'address', 'lat', 'lng', 'recognition_level', 'year_recognized',
            'year_built', 'visiting_hours', 'ticket_price_vnd', 'website', 'status', 'cover_media_id', 'view_count'];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        $query->with('province')
            ->when($filters['province'], fn ($q, $v) => $q->whereHas('province', fn ($p) => $p->where('code', $v)))
            ->when($filters['type'], fn ($q, $v) => $q->where('site_type', $v));
    }

    protected function rowExtras(Model $model): array
    {
        return ['site_type' => $model->site_type, 'province' => $model->province?->localizedName()];
    }

    protected function extraForm(Model $model): array
    {
        $model->loadMissing(['figures', 'timelineEvents.translations']);

        return [
            'figures' => $model->figures->map(fn ($f) => ['figure_id' => $f->id, 'relation_type' => $f->pivot->relation_type])->all(),
            'events' => HeritagePresenter::events($model->timelineEvents),
        ];
    }

    protected function formOptions(): array
    {
        return [
            'figures' => $this->lookup->options(HistoricalFigure::class),
            'enums' => HeritagePresenter::commonEnums() + [
                'site_types' => HeritagePresenter::enumList('site_types', HeritageSite::TYPES),
                'levels' => HeritagePresenter::enumList('levels', HeritageSite::LEVELS),
                'relation_types' => HeritagePresenter::enumList('relation_types', ['sang_lap', 'duoc_tho', 'gan_lien', 'trung_tu', 'sinh_ra', 'an_tang']),
            ],
        ];
    }

    public function store(SiteRequest $request): RedirectResponse
    {
        return $this->redirectAfterSave($this->writer->saveSite($request->validated(), $request->user()));
    }

    public function edit(HeritageSite $site): Response
    {
        return $this->form($site);
    }

    public function update(SiteRequest $request, HeritageSite $site): RedirectResponse
    {
        return $this->redirectAfterSave($this->writer->saveSite($request->validated(), $request->user(), $site));
    }

    public function destroy(Request $request, HeritageSite $site): RedirectResponse
    {
        return $this->destroyModel($request, $site);
    }
}
