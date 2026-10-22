<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\FigureRequest;
use App\Models\HistoricalFigure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class FigureController extends HeritageAdminController
{
    protected function modelClass(): string { return HistoricalFigure::class; }

    protected function pageFolder(): string { return 'Figures'; }

    protected function routeKey(): string { return 'figures'; }

    protected function propName(): string { return 'figure'; }

    protected function columns(): array
    {
        return ['slug', 'figure_type', 'birth_year', 'death_year', 'period_id', 'province_id', 'portrait_media_id', 'status', 'view_count'];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        $query->when($filters['type'], fn ($q, $v) => $q->where('figure_type', $v));
    }

    protected function rowExtras(Model $model): array
    {
        return ['figure_type' => $model->figure_type, 'birth_year' => $model->birth_year, 'death_year' => $model->death_year];
    }

    protected function extraForm(Model $model): array
    {
        $model->loadMissing('timelineEvents.translations');

        return ['events' => HeritagePresenter::events($model->timelineEvents)];
    }

    protected function formOptions(): array
    {
        return [
            'enums' => HeritagePresenter::commonEnums() + [
                'figure_types' => HeritagePresenter::enumList('figure_types', HistoricalFigure::TYPES),
            ],
        ];
    }

    public function store(FigureRequest $request): RedirectResponse
    {
        return $this->redirectAfterSave($this->writer->saveFigure($request->validated(), $request->user()));
    }

    public function edit(HistoricalFigure $figure): Response
    {
        return $this->form($figure);
    }

    public function update(FigureRequest $request, HistoricalFigure $figure): RedirectResponse
    {
        return $this->redirectAfterSave($this->writer->saveFigure($request->validated(), $request->user(), $figure));
    }

    public function destroy(Request $request, HistoricalFigure $figure): RedirectResponse
    {
        return $this->destroyModel($request, $figure);
    }
}
