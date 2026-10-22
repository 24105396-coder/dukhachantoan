<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuideRequest;
use App\Http\Resources\GuideCard;
use App\Http\Resources\GuideDetail;
use App\Models\HazardType;
use App\Models\ResponseGuide;
use App\Services\AuditLogger;
use App\Services\LookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** CRUD hướng dẫn ứng phó 2 ngôn ngữ, xuất bản/ẩn. */
class GuideController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, ['hazard', 'status', 'q']);
        $guides = ResponseGuide::query()->with(GuideCard::RELATIONS)
            ->when($filters['hazard'], fn ($q, $v) => $q->whereHas('hazardType', fn ($h) => $h->where('code', $v)))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('slug', 'like', like_contains($v))
                ->orWhereHas('translations', fn ($t) => $t->where('title', 'like', like_contains($v)))))
            ->orderBy('hazard_type_id')->orderBy('sort_order')->paginate(30)->withQueryString();

        return Inertia::render('Admin/Guides/Index', [
            'guides' => GuideCard::paginate($guides),
            'filters' => $filters,
            'hazards' => $lookup->hazards(),
        ]);
    }

    public function create(LookupService $lookup): Response
    {
        return Inertia::render('Admin/Guides/Form', $this->formProps($lookup) + ['guide' => null]);
    }

    public function store(GuideRequest $request): RedirectResponse
    {
        $guide = new ResponseGuide(['created_by' => $request->user()->id]);
        $this->save($guide, $request->validated());
        $this->audit->log('admin.guide.create', $guide);

        return redirect(localized_route('admin.guides.index'))->with('success', __('messages.admin.created'));
    }

    public function edit(LookupService $lookup, ResponseGuide $guide): Response
    {
        $guide->load(GuideCard::RELATIONS);

        return Inertia::render('Admin/Guides/Form', $this->formProps($lookup) + [
            'guide' => (new GuideDetail($guide))->withTranslations()->resolve(request()) + ['sort_order' => (int) $guide->sort_order],
        ]);
    }

    public function update(GuideRequest $request, ResponseGuide $guide): RedirectResponse
    {
        $this->save($guide, $request->validated());
        $this->audit->log('admin.guide.update', $guide);

        return redirect(localized_route('admin.guides.index'))->with('success', __('messages.admin.updated'));
    }

    /** Xuất bản ↔ bản nháp. */
    public function publish(ResponseGuide $guide): RedirectResponse
    {
        $guide->forceFill(['status' => $guide->status === 'published' ? 'draft' : 'published'])->save();
        $this->audit->log('admin.guide.publish', $guide, ['status' => $guide->status]);

        return back()->with('success', __('messages.admin.updated'));
    }

    public function destroy(ResponseGuide $guide): RedirectResponse
    {
        $this->audit->log('admin.guide.delete', $guide, ['slug' => $guide->slug]);
        $guide->delete();

        return back()->with('success', __('messages.admin.deleted'));
    }

    private function save(ResponseGuide $guide, array $data): void
    {
        $guide->fill([
            'slug' => $data['slug'],
            'hazard_type_id' => HazardType::query()->where('code', $data['hazard_code'])->value('id'),
            'min_severity' => (int) $data['min_severity'],
            'audience' => $data['audience'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
        ])->save();
        $guide->syncTranslations(collect($data['translations'])->map(fn ($t) => collect($t)->map(
            fn ($v) => is_array($v) ? array_values(array_filter($v, fn ($s) => trim((string) $s) !== '')) : $v
        )->all())->filter(fn ($t) => ! empty($t['title']))->all());
    }

    private function formProps(LookupService $lookup): array
    {
        return [
            'hazards' => $lookup->hazards(),
            'audiences' => $this->enumOptions('audiences', ResponseGuide::AUDIENCES),
            'statuses' => $this->enumOptions('statuses', ResponseGuide::STATUSES),
        ];
    }
}
