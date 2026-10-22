<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TripController as PublicTripController;
use App\Http\Resources\TripCard;
use App\Http\Resources\TripDetail;
use App\Http\Resources\ZoneGeo;
use App\Models\Trip;
use App\Services\LookupService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Hành trình trong vùng phụ trách: lọc theo tỉnh, vùng, ngày, mức rủi ro, trạng thái. */
class TripController extends Controller
{
    public const PER_PAGE = 20;
    public const FILTERS = ['province', 'zone', 'date_from', 'date_to', 'risk_level', 'status', 'q'];

    public function index(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, self::FILTERS);
        $trips = Trip::query()->visibleTo($request->user())->withCardData()
            ->when($filters['province'], fn ($q, $v) => $q->whereHas('legs.zone.province', fn ($p) => $p->where('code', $v)))
            ->when($filters['zone'], fn ($q, $v) => $q->whereHas('legs.zone', fn ($z) => $z->where('code', $v)))
            ->when($this->date($filters['date_from']), fn ($q, $v) => $q->whereDate('end_date', '>=', $v))
            ->when($this->date($filters['date_to']), fn ($q, $v) => $q->whereDate('start_date', '<=', $v))
            ->when($filters['risk_level'] !== null, fn ($q) => $q->where('risk_level_cached', '>=', (int) $filters['risk_level']))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('code', 'like', like_contains($v))->orWhere('title', 'like', like_contains($v))))
            ->orderByDesc('risk_level_cached')->orderByDesc('start_date')
            ->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Admin/Trips/Index', [
            'trips' => TripCard::paginate($trips),
            'filters' => $filters,
            'provinces' => $lookup->provinces(),
            'zones' => $lookup->zones(),
            'statuses' => $this->enumOptions('trip_statuses', Trip::STATUSES),
        ]);
    }

    public function show(Request $request, Trip $trip): Response
    {
        $this->authorize('view', $trip);
        PublicTripController::loadDetail($trip);

        return Inertia::render('Admin/Trips/Show', [
            'trip' => TripDetail::one($trip),
            'zonesGeo' => ZoneGeo::collection($trip->legs->pluck('zone')->filter()->unique('id')->values()),
        ]);
    }

    private function date(?string $value): ?string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
