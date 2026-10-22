<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckinResponseRequest;
use App\Http\Resources\CheckinItem;
use App\Models\SafetyCheckin;
use App\Services\CheckinService;
use App\Services\LookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Check-in an toàn: lọc và phản hồi (agency chỉ tỉnh mình). */
class CheckinController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, ['status', 'response_status', 'province']);
        $checkins = SafetyCheckin::query()->with(CheckinItem::RELATIONS)
            ->when($request->user()->scopedProvinceId(), fn ($q, $p) => $q->inProvince($p))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['response_status'], fn ($q, $v) => $q->where('response_status', $v))
            ->when($filters['province'], fn ($q, $v) => $q->whereHas('trip.legs.zone.province', fn ($p) => $p->where('code', $v)))
            ->orderByRaw("CASE WHEN status = 'need_help' AND response_status <> 'closed' THEN 0 ELSE 1 END")
            ->latest('checked_at')->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Admin/Checkins/Index', [
            'checkins' => CheckinItem::paginate($checkins),
            'filters' => $filters,
            'provinces' => $lookup->provinces(),
            'statuses' => $this->enumOptions('checkin_statuses', SafetyCheckin::STATUSES),
            'responseStatuses' => $this->enumOptions('response_statuses', SafetyCheckin::RESPONSE_STATUSES),
        ]);
    }

    public function respond(CheckinResponseRequest $request, CheckinService $service, SafetyCheckin $checkin): RedirectResponse
    {
        $this->authorize('respond', $checkin);
        $service->respond($checkin, $request->user(), $request->validated('response_status'), $request->validated('response_note'));

        return back()->with('success', __('messages.checkins.responded'));
    }
}
