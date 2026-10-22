<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertCard;
use App\Http\Resources\AlertDetail;
use App\Http\Resources\ContactItem;
use App\Http\Resources\ZoneGeo;
use App\Models\Alert;
use App\Models\EmergencyContact;
use App\Services\LookupService;
use App\Services\Queries\AlertSearch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Cảnh báo công khai: danh sách có bộ lọc và chi tiết (nháp không hiển thị). */
class AlertController extends Controller
{
    public const PER_PAGE = 12;
    public const FILTERS = ['q', 'severity', 'hazard', 'province', 'status', 'simulated'];

    public function index(Request $request, AlertSearch $search, LookupService $lookup): Response
    {
        $filters = $this->filters($request, self::FILTERS);
        $filters['status'] = in_array($filters['status'], AlertSearch::PUBLIC_STATUSES, true) ? $filters['status'] : 'active';

        return Inertia::render('Alerts/Index', [
            'alerts' => AlertCard::paginate($search->query($filters)->paginate(self::PER_PAGE)->withQueryString()),
            'filters' => $filters,
            'hazards' => $lookup->hazards(),
            'provinces' => $lookup->provinces(),
        ]);
    }

    public function show(Alert $alert): Response
    {
        abort_if($alert->status === Alert::STATUS_DRAFT, 404);
        $alert->load(AlertCard::RELATIONS);
        $provinceIds = $alert->zones->pluck('province_id')->filter()->unique();

        return Inertia::render('Alerts/Show', [
            'alert' => AlertDetail::one($alert),
            'zonesGeo' => ZoneGeo::collection($alert->zones->load('province')),
            'nearbyContacts' => ContactItem::list(EmergencyContact::query()->with(ContactItem::RELATIONS)
                ->whereIn('province_id', $provinceIds)->orderBy('sort_order')->limit(12)->get()),
        ]);
    }
}
