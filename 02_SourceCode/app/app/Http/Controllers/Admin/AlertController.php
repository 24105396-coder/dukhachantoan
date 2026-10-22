<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AlertRequest;
use App\Http\Requests\Admin\AlertTransitionRequest;
use App\Http\Resources\AlertCard;
use App\Http\Resources\AlertDetail;
use App\Http\Resources\MatchItem;
use App\Models\Alert;
use App\Models\AlertMatch;
use App\Models\Notification;
use App\Services\AlertService;
use App\Services\LookupService;
use App\Services\Queries\AlertSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Soạn, phát hành, cập nhật, hết hiệu lực, hủy cảnh báo (agency giới hạn tỉnh qua AlertPolicy/scope). */
class AlertController extends Controller
{
    public const PER_PAGE = 20;
    public const FILTERS = ['q', 'status', 'severity', 'hazard', 'province', 'simulated'];

    public function __construct(private readonly AlertService $alerts)
    {
    }

    public function index(Request $request, AlertSearch $search, LookupService $lookup): Response
    {
        $user = $request->user();
        $filters = $this->filters($request, self::FILTERS);
        $filters['status'] ??= 'all';
        $query = $search->query($filters, true)
            ->when($user->scopedProvinceId(), fn ($q, $p) => $q->where(fn ($w) => $w->inProvince($p)->orWhere('created_by', $user->id)));

        return Inertia::render('Admin/Alerts/Index', [
            'alerts' => AlertCard::paginate($query->paginate(self::PER_PAGE)->withQueryString()),
            'filters' => $filters,
            'hazards' => $lookup->hazards(),
            'provinces' => $lookup->provinces(),
            'statuses' => $this->enumOptions('alert_statuses', Alert::STATUSES),
        ]);
    }

    public function create(LookupService $lookup): Response
    {
        $this->authorize('create', Alert::class);

        return Inertia::render('Admin/Alerts/Form', $this->formProps($lookup) + ['alert' => null]);
    }

    public function store(AlertRequest $request): RedirectResponse
    {
        $this->authorize('create', Alert::class);
        $alert = $this->alerts->create($request->validated(), $request->user());

        return redirect(localized_route('admin.alerts.show', ['alert' => $alert->code]))->with('success', __('messages.alerts.created'));
    }

    public function show(Request $request, Alert $alert): Response
    {
        $this->authorize('view', $alert);
        $alert->load(AlertCard::RELATIONS);
        $matches = AlertMatch::query()->where('alert_id', $alert->id)->with(['leg', 'trip', 'alert' => fn ($q) => $q->with(AlertCard::RELATIONS)])
            ->latest('id')->limit(200)->get();

        return Inertia::render('Admin/Alerts/Show', [
            'alert' => (new AlertDetail($alert))->withAffectedTrips($matches->pluck('trip_id')->unique()->count())->resolve($request),
            'matches' => MatchItem::list($matches),
            'notifications' => $this->notificationStats($alert),
            'transitions' => array_values(Alert::TRANSITIONS[$alert->status] ?? []),
            'can' => ['update' => $request->user()->can('update', $alert)],
        ]);
    }

    public function edit(Request $request, LookupService $lookup, Alert $alert): Response
    {
        $this->authorize('update', $alert);
        $alert->load(AlertCard::RELATIONS);

        return Inertia::render('Admin/Alerts/Form', $this->formProps($lookup) + [
            'alert' => AlertCard::one($alert) + [
                'hazard_code' => $alert->hazardType?->code,
                'zone_codes' => $alert->zones->pluck('code')->values()->all(),
                'translations' => $alert->translations->mapWithKeys(fn ($t) => [$t->locale => [
                    'headline' => $t->headline, 'description' => $t->description, 'instructions' => $t->instructions,
                ]])->all(),
            ],
        ]);
    }

    public function update(AlertRequest $request, Alert $alert): RedirectResponse
    {
        $this->authorize('update', $alert);
        $this->alerts->update($alert, $request->validated(), $request->user());

        return redirect(localized_route('admin.alerts.show', ['alert' => $alert->code]))->with('success', __('messages.alerts.updated'));
    }

    /** Nút Phát hành / Cập nhật / Hết hiệu lực / Hủy. */
    public function transition(AlertTransitionRequest $request, Alert $alert): RedirectResponse
    {
        $this->authorize('update', $alert);
        $result = $this->alerts->transition($alert, $request->validated('status'), $request->user());

        return back()->with('success', __('messages.alerts.transitioned', [
            'status' => __('enums.alert_statuses.'.$alert->status),
            'matches' => $result['matches'] ?? 0,
            'notifications' => $result['notifications'] ?? 0,
        ]));
    }

    private function notificationStats(Alert $alert): array
    {
        $rows = Notification::query()->where('alert_id', $alert->id)
            ->selectRaw('status, channel, COUNT(*) AS total')->groupBy('status', 'channel')->get();

        return [
            'total' => (int) $rows->sum('total'),
            'by_status' => collect(Notification::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) $rows->where('status', $s)->sum('total')])->all(),
            'by_channel' => collect(Notification::CHANNELS)->mapWithKeys(fn ($c) => [$c => (int) $rows->where('channel', $c)->sum('total')])->all(),
        ];
    }

    private function formProps(LookupService $lookup): array
    {
        return [
            'hazards' => $lookup->hazards(),
            'zones' => $lookup->zones(),
            'severities' => collect(Alert::SEVERITIES)->map(fn ($key, $level) => ['level' => $level, 'label' => __('enums.severity.'.$key)])->values()->all(),
            'defaults' => [
                'severity' => 2,
                'starts_at' => now()->startOfHour()->toIso8601String(),
                'ends_at' => now()->startOfHour()->addDay()->toIso8601String(),
                'source_name' => request()->user()?->organization ?: __('messages.alerts.default_source'),
            ],
        ];
    }
}
