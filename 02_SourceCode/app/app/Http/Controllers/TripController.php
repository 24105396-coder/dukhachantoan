<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckinRequest;
use App\Http\Requests\TripRequest;
use App\Http\Resources\AlertCard;
use App\Http\Resources\CheckinItem;
use App\Http\Resources\DestinationCard;
use App\Http\Resources\TripCard;
use App\Http\Resources\TripDetail;
use App\Http\Resources\WeatherNow;
use App\Http\Resources\ZoneGeo;
use App\Models\AlertMatch;
use App\Models\Destination;
use App\Models\SafetyCheckin;
use App\Models\Trip;
use App\Models\TripLeg;
use App\Services\CheckinService;
use App\Services\LookupService;
use App\Services\TripService;
use App\Services\WeatherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Hành trình của du khách / đoàn của đơn vị lữ hành (F1, F8). Quyền theo TripPolicy. */
class TripController extends Controller
{
    public const PER_PAGE = 10;
    public const UPCOMING_WEATHER = 4;

    public function __construct(private readonly TripService $trips)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request, ['status', 'q']);
        $trips = Trip::query()->visibleTo($request->user())->withCardData()
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', like_contains($v))->orWhere('code', 'like', like_contains($v))))
            ->orderByRaw("FIELD(status, 'active', 'draft', 'completed', 'cancelled')")->orderByDesc('start_date')
            ->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Trips/Index', [
            'trips' => TripCard::paginate($trips),
            'filters' => $filters,
            'statuses' => $this->enumOptions('trip_statuses', Trip::STATUSES),
        ]);
    }

    public function create(Request $request, LookupService $lookup): Response
    {
        return Inertia::render('Trips/Form', $this->formProps($lookup) + ['trip' => null]);
    }

    public function store(TripRequest $request): RedirectResponse
    {
        $trip = $this->trips->create($request->user(), $request->validated());

        return redirect(localized_route('trips.show', ['trip' => $trip->code]))->with('success', __('messages.trips.created'));
    }

    public function show(Request $request, Trip $trip, WeatherService $weather): Response
    {
        $this->authorize('view', $trip);
        self::loadDetail($trip);

        return Inertia::render('Trips/Show', [
            'trip' => TripDetail::one($trip),
            'zonesGeo' => ZoneGeo::collection($trip->legs->pluck('zone')->filter()->unique('id')->values()),
            'weather' => WeatherNow::list($trip->legs->filter(fn (TripLeg $l) => $l->ends_at === null || $l->ends_at->isFuture())
                ->pluck('zone')->filter()->unique('id')->take(self::UPCOMING_WEATHER)->map(fn ($z) => $weather->now($z))->filter()),
        ]);
    }

    public function edit(Request $request, LookupService $lookup, Trip $trip): Response
    {
        $this->authorize('update', $trip);
        self::loadDetail($trip);

        return Inertia::render('Trips/Form', $this->formProps($lookup) + ['trip' => TripDetail::one($trip)]);
    }

    public function update(TripRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);
        $this->trips->update($trip, $request->validated(), $request->user());

        return redirect(localized_route('trips.show', ['trip' => $trip->code]))->with('success', __('messages.trips.updated'));
    }

    public function cancel(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('cancel', $trip);
        $this->trips->cancel($trip, $request->user());

        return back()->with('success', __('messages.trips.cancelled'));
    }

    public function checkin(CheckinRequest $request, CheckinService $checkins, Trip $trip): RedirectResponse
    {
        $this->authorize('checkin', $trip);
        $checkin = $checkins->create($trip, $request->user(), $request->validated(), 'app');
        $response = back()->with('checkin', CheckinItem::one($checkin->load(CheckinItem::RELATIONS)));
        if ($checkin->status !== SafetyCheckin::STATUS_NEED_HELP) {
            return $response->with('success', __('messages.checkins.recorded'));
        }
        $provinces = $checkins->responderProvinceNames($trip);

        return $response
            ->with('success', __('messages.checkins.need_help_sent', [
                'id' => $checkin->id,
                'provinces' => $provinces !== [] ? implode(', ', $provinces) : __('messages.checkins.national_agency'),
            ]))
            ->with('success_link', localized_route('requests.index', ['moi' => 'checkin:'.$checkin->id]));
    }

    public function acknowledge(Request $request, Trip $trip, AlertMatch $match): RedirectResponse
    {
        $this->authorize('acknowledge', $trip);
        $this->trips->acknowledge($trip, $match);

        return back()->with('success', __('messages.trips.acknowledged'));
    }

    public function ical(Request $request, Trip $trip): HttpResponse
    {
        $this->authorize('view', $trip);

        return response($this->trips->ical($trip), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$trip->code.'.ics"',
        ]);
    }

    /** Nạp quan hệ cho TripDetail (một lần, tránh N+1). */
    public static function loadDetail(Trip $trip): Trip
    {
        $alertRelations = fn ($q) => $q->with(AlertCard::RELATIONS);
        $trip->load([
            'user:id,name', 'partner:id,name,organization', 'members',
            'latestCheckin' => fn ($q) => $q->with(CheckinItem::RELATIONS),
            'legs.zone.province', 'legs.destination.translations', 'legs.destination.zone', 'legs.destination.province',
            'legs.matches.alert' => $alertRelations,
            'matches.alert' => $alertRelations, 'matches.leg',
            'checkins' => fn ($q) => $q->with(CheckinItem::RELATIONS),
        ]);
        $trip->active_alerts_count = $trip->matches
            ->filter(fn (AlertMatch $m) => $m->status !== AlertMatch::STATUS_RESOLVED && $m->alert?->isLive())
            ->pluck('alert_id')->unique()->count();

        return $trip;
    }

    private function formProps(LookupService $lookup): array
    {
        return [
            'zones' => $lookup->zones(),
            'destinations' => DestinationCard::list(Destination::query()->with(DestinationCard::RELATIONS)
                ->orderByDesc('is_popular')->orderBy('slug')->get()),
            'defaults' => [
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
                'preferred_locale' => app()->getLocale(),
                'group_size' => 1,
                'status' => Trip::STATUS_ACTIVE,
            ],
            'ageGroups' => $this->enumOptions('age_groups', ['child', 'adult', 'senior']),
        ];
    }
}
