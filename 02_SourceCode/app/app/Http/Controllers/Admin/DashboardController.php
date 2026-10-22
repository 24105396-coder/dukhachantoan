<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlertCard;
use App\Http\Resources\CheckinItem;
use App\Http\Resources\TripCard;
use App\Http\Resources\ZoneGeo;
use App\Models\Alert;
use App\Models\SafetyCheckin;
use App\Models\Trip;
use App\Models\Zone;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bảng điều khiển cơ quan hỗ trợ (F9). Agency chỉ thấy số liệu tỉnh mình (users.province_id); admin toàn quốc.
 */
class DashboardController extends Controller
{
    public const AFFECTED_TRIPS = 20;
    public const RECENT_ALERTS = 8;
    public const NEED_HELP = 20;

    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $provinceId = $request->user()->scopedProvinceId();

        return Inertia::render('Admin/Dashboard', [
            'kpis' => $dashboard->kpis($provinceId),
            'affectedTrips' => TripCard::list($this->affectedTrips($provinceId)),
            'needHelp' => CheckinItem::list(SafetyCheckin::query()->with(CheckinItem::RELATIONS)
                ->where('status', SafetyCheckin::STATUS_NEED_HELP)->where('response_status', '!=', 'closed')
                ->when($provinceId, fn ($q, $v) => $q->inProvince($v))
                ->latest('checked_at')->limit(self::NEED_HELP)->get()),
            'recentAlerts' => AlertCard::list(Alert::query()->with(AlertCard::RELATIONS)->where('status', '!=', Alert::STATUS_DRAFT)
                ->when($provinceId, fn ($q, $v) => $q->inProvince($v))
                ->latest('updated_at')->limit(self::RECENT_ALERTS)->get()),
            'zonesGeo' => ZoneGeo::collection(Zone::query()->active()->when($provinceId, fn ($q, $v) => $q->where('province_id', $v))->orderBy('id')->get()),
            'queueHealth' => $dashboard->queueHealth(),
            'scope' => ['province' => $request->user()->isAgency() ? $request->user()->province?->brief() : null],
        ]);
    }

    private function affectedTrips(?int $provinceId)
    {
        return Trip::query()->ongoing()->withCardData()
            ->when($provinceId, fn ($q, $v) => $q->inProvince($v))
            ->whereHas('matches', fn ($q) => $q->where('status', '!=', 'resolved')->whereHas('alert', fn ($a) => $a->live()))
            ->orderByDesc('risk_level_cached')->orderBy('start_date')->limit(self::AFFECTED_TRIPS)->get();
    }
}
