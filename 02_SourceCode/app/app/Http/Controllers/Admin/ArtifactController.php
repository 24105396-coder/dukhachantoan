<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ArtifactRequest;
use App\Models\Artifact;
use App\Models\HeritageSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ArtifactController extends HeritageAdminController
{
    protected function modelClass(): string { return Artifact::class; }

    protected function pageFolder(): string { return 'Artifacts'; }

    protected function routeKey(): string { return 'artifacts'; }

    protected function propName(): string { return 'artifact'; }

    protected function columns(): array
    {
        return ['slug', 'site_id', 'inventory_code', 'artifact_type', 'material', 'period_id', 'year_from', 'year_to', 'height_cm',
            'width_cm', 'depth_cm', 'weight_kg', 'is_national_treasure', 'model3d_url', 'status', 'cover_media_id', 'view_count'];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        $query->with(['site.interpretations' => fn ($q) => $q->where('depth_level', 1)])
            ->when($filters['type'], fn ($q, $v) => $q->where('artifact_type', $v));
    }

    protected function rowExtras(Model $model): array
    {
        return ['artifact_type' => $model->artifact_type, 'site' => $model->site ? ['id' => $model->site->id, 'slug' => $model->site->slug, 'title' => $model->site->title] : null, 'is_national_treasure' => (bool) $model->is_national_treasure];
    }

    protected function formOptions(): array
    {
        return [
            'sites' => $this->lookup->options(HeritageSite::class),
            'enums' => HeritagePresenter::commonEnums() + [
                'artifact_types' => HeritagePresenter::enumList('artifact_types', Artifact::TYPES),
            ],
        ];
    }

    public function store(ArtifactRequest $request): RedirectResponse
    {
        return $this->redirectAfterSave($this->writer->saveArtifact($request->validated(), $request->user()));
    }

    public function edit(Artifact $artifact): Response
    {
        return $this->form($artifact);
    }

    public function update(ArtifactRequest $request, Artifact $artifact): RedirectResponse
    {
        return $this->redirectAfterSave($this->writer->saveArtifact($request->validated(), $request->user(), $artifact));
    }

    public function destroy(Request $request, Artifact $artifact): RedirectResponse
    {
        return $this->destroyModel($request, $artifact);
    }
}
