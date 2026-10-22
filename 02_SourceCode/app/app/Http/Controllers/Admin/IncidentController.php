<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IncidentReviewRequest;
use App\Http\Resources\IncidentItem;
use App\Models\IncidentReport;
use App\Services\IncidentService;
use App\Services\LookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Duyệt sự cố du khách báo; tạo cảnh báo nháp từ sự cố đã xác minh. */
class IncidentController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, ['status', 'province', 'hazard']);
        $incidents = IncidentReport::query()->with(IncidentItem::RELATIONS)
            ->when($request->user()->scopedProvinceId(), fn ($q, $p) => $q->whereHas('zone', fn ($z) => $z->where('province_id', $p)))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['province'], fn ($q, $v) => $q->whereHas('zone.province', fn ($p) => $p->where('code', $v)))
            ->when($filters['hazard'], fn ($q, $v) => $q->whereHas('hazardType', fn ($h) => $h->where('code', $v)))
            ->orderByRaw("FIELD(status, 'pending', 'verified', 'rejected')")->latest('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Admin/Incidents/Index', [
            'incidents' => IncidentItem::paginate($incidents),
            'filters' => $filters,
            'provinces' => $lookup->provinces(),
            'hazards' => $lookup->hazards(),
            'statuses' => $this->enumOptions('incident_statuses', IncidentReport::STATUSES),
        ]);
    }

    public function review(IncidentReviewRequest $request, IncidentService $service, IncidentReport $incident): RedirectResponse
    {
        $this->authorize('review', $incident);
        $service->review($incident, $request->user(), $request->validated('status'), $request->validated('review_note'));

        return back()->with('success', __('messages.incidents.reviewed'));
    }

    public function createAlert(Request $request, IncidentService $service, IncidentReport $incident): RedirectResponse
    {
        $this->authorize('review', $incident);
        $alert = $service->createAlert($incident, $request->user());

        return redirect(localized_route('admin.alerts.edit', ['alert' => $alert->code]))->with('success', __('messages.incidents.alert_created'));
    }
}
