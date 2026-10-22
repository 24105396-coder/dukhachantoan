<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourRouteRequest;
use App\Http\Requests\Admin\TourSlotRequest;
use App\Http\Resources\MediaItemResource;
use App\Http\Resources\RouteCardResource;
use App\Http\Resources\SlotItemResource;
use App\Models\HeritageSite;
use App\Models\MediaAsset;
use App\Models\TourRoute;
use App\Models\TourSlot;
use App\Services\Admin\TourRouteWriter;
use App\Services\LookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Contributor chỉ thấy và sửa tuyến của mình (TourRoutePolicy). */
class TourController extends Controller
{
    public function __construct(private readonly TourRouteWriter $writer, private readonly LookupService $lookup)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TourRoute::class);
        $user = $request->user();
        $filters = $this->filters($request, ['q', 'status']);

        $routes = TourRoute::query()->with([...RouteCardResource::eager(), 'owner:id,name'])->withCount(['stops', 'slots'])
            ->when(! $user->isStaff(), fn ($q) => $q->where('created_by', $user->id))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'], fn ($q, $v) => $q->whereHas('translations', fn ($t) => $t->where('name', 'like', like_contains($v))))
            ->latest('updated_at')->paginate(20)->withQueryString()
            ->through(fn ($r) => RouteCardResource::one($r) + [
                'slots_count' => (int) $r->slots_count,
                'created_by' => $r->created_by,
                'owner' => $r->owner ? ['name' => $r->owner->name] : null,
            ]);

        return Inertia::render('Admin/Tours/Index', [
            'routes' => $routes->toArray(),
            'filters' => $filters,
            'canCreate' => $user->can('create', TourRoute::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', TourRoute::class);

        return $this->form(null);
    }

    public function store(TourRouteRequest $request): RedirectResponse
    {
        $this->authorize('create', TourRoute::class);
        $route = $this->writer->save($request->validated(), $request->user());

        return redirect(localized_route('admin.tours.edit', ['route' => $route->slug]))->with('success', __('messages.admin.created'));
    }

    public function edit(TourRoute $route): Response
    {
        $this->authorize('update', $route);

        return $this->form($route);
    }

    public function update(TourRouteRequest $request, TourRoute $route): RedirectResponse
    {
        $this->authorize('update', $route);
        $this->writer->save($request->validated(), $request->user(), $route);

        return redirect(localized_route('admin.tours.edit', ['route' => $route->slug]))->with('success', __('messages.admin.updated'));
    }

    public function destroy(Request $request, TourRoute $route): RedirectResponse
    {
        $this->authorize('delete', $route);
        $route->delete();

        return redirect(localized_route('admin.tours.index'))->with('success', __('messages.admin.deleted'));
    }

    public function storeSlot(TourSlotRequest $request, TourRoute $route): RedirectResponse
    {
        $this->authorize('manageSlots', $route);
        $this->writer->addSlot($route, $request->validated(), $request->user());

        return back()->with('success', __('messages.admin.slot_created'));
    }

    public function destroySlot(Request $request, TourRoute $route, TourSlot $slot): RedirectResponse
    {
        $this->authorize('manageSlots', $route);
        abort_unless((int) $slot->route_id === (int) $route->id, 404);

        return $this->writer->deleteSlot($slot, $request->user())
            ? back()->with('success', __('messages.admin.slot_deleted'))
            : back()->with('error', __('messages.admin.slot_has_bookings'));
    }

    private function form(?TourRoute $route): Response
    {
        $payload = null;
        if ($route) {
            $route->load(['translations', 'stops', 'media.captions', 'slots' => fn ($q) => $q->where('starts_at', '>=', now()->subDays(1))]);
            $payload = $route->only(['id', 'slug', 'province_id', 'theme_tag_id', 'duration_minutes', 'distance_km', 'difficulty', 'price_vnd', 'default_capacity', 'status', 'created_by']) + [
                'translations' => collect(['vi', 'en'])->mapWithKeys(fn ($l) => [$l => $route->translations->firstWhere('locale', $l)?->only(['name', 'description', 'highlights'])])->all(),
                'stops' => $route->stops->map(fn ($s) => $s->only(['site_id', 'stop_order', 'dwell_minutes', 'note']))->values()->all(),
                'slots' => SlotItemResource::list($route->slots),
                'media' => MediaItemResource::list($route->media),
            ];
        }

        return Inertia::render('Admin/Tours/Form', [
            'route' => $payload,
            'sites' => $this->lookup->options(HeritageSite::class),
            'tags' => $this->lookup->tags(),
            'provinces' => $this->lookup->provinces(),
            'enums' => [
                'difficulties' => HeritagePresenter::enumList('difficulties', ['de', 'vua', 'kho']),
                'statuses' => HeritagePresenter::enumList('statuses', ['draft', 'published', 'archived']),
                'licenses' => HeritagePresenter::enumList('licenses', MediaAsset::LICENSES),
            ],
        ]);
    }
}
