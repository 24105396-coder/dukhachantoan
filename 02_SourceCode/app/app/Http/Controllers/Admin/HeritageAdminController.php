<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\HeritageWriter;
use App\Services\LookupService;
use App\Services\Queries\HeritageQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Khung CRUD chung cho ba thực thể di sản; lớp con khai báo model, trang và cột. */
abstract class HeritageAdminController extends Controller
{
    public function __construct(protected readonly HeritageWriter $writer, protected readonly LookupService $lookup)
    {
    }

    abstract protected function modelClass(): string;

    /** Ví dụ 'Sites' → Admin/Sites/Index, Admin/Sites/Form. */
    abstract protected function pageFolder(): string;

    /** Ví dụ 'sites' → route admin.sites.* và prop 'sites'/'site'. */
    abstract protected function routeKey(): string;

    abstract protected function propName(): string;

    abstract protected function columns(): array;

    abstract protected function formOptions(): array;

    protected function extraForm(Model $model): array
    {
        return [];
    }

    protected function rowExtras(Model $model): array
    {
        return [];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request, ['q', 'status', 'province', 'type']);
        $class = $this->modelClass();
        $query = $class::query()->with('interpretations:id,subject_type,subject_id,locale,depth_level,title')->withCount('media')
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v));
        HeritageQuery::keyword($query, $filters['q']);
        $this->applyFilters($query, $filters);

        $items = $query->latest('updated_at')->paginate(20)->withQueryString()
            ->through(fn ($m) => HeritagePresenter::row($m) + $this->rowExtras($m));

        // formOptions() gồm enums riêng (artifact_types, figure_types…) và danh sách chọn (sites) cho bộ lọc
        return Inertia::render('Admin/'.$this->pageFolder().'/Index', array_merge($this->formOptions(), [
            $this->routeKey() => $items->toArray(),
            'filters' => $filters,
            'provinces' => $this->lookup->provinces(),
        ]));
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    protected function form(?Model $model): Response
    {
        $payload = $model ? HeritagePresenter::form($model, $this->columns()) + $this->extraForm($model) : null;

        return Inertia::render('Admin/'.$this->pageFolder().'/Form', array_merge([
            $this->propName() => $payload,
            'provinces' => $this->lookup->provinces(),
            'periods' => $this->lookup->periods(),
            'tags' => $this->lookup->tags(),
        ], $this->formOptions()));
    }

    protected function redirectAfterSave(Model $model): RedirectResponse
    {
        return redirect(localized_route('admin.'.$this->routeKey().'.edit', [$this->propName() => $model->slug]))
            ->with('success', __('messages.admin.updated'));
    }

    protected function destroyModel(Request $request, Model $model): RedirectResponse
    {
        $this->writer->delete($model, $request->user());

        return redirect(localized_route('admin.'.$this->routeKey().'.index'))->with('success', __('messages.admin.deleted'));
    }
}
