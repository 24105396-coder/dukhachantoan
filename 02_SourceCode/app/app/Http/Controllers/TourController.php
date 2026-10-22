<?php

namespace App\Http\Controllers;

use App\Http\Resources\RouteCardResource;
use App\Http\Resources\RouteDetailResource;
use App\Models\TourRoute;
use App\Services\BookingService;
use App\Services\LookupService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TourController extends Controller
{
    public function index(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, ['province', 'difficulty', 'q']);

        $routes = TourRoute::published()->with(RouteCardResource::eager())->withCount('stops')
            ->when($filters['province'], fn ($q, $v) => $q->whereHas('province', fn ($p) => $p->where('code', $v)))
            ->when($filters['difficulty'], fn ($q, $v) => $q->where('difficulty', $v))
            ->when($filters['q'], function ($q, $v) {
                $like = like_contains($v);
                $q->whereHas('translations', fn ($t) => $t->where('name', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->orderBy('price_vnd')->orderBy('id')
            ->paginate(9)->withQueryString();

        return Inertia::render('Tours/Index', [
            'routes' => RouteCardResource::paginate($routes),
            'filters' => $filters,
            'provinces' => $this->routeProvinces($lookup),
            'difficulties' => $this->enumOptions('difficulties', ['de', 'vua', 'kho']),
        ]);
    }

    /** Chỉ các tỉnh có tuyến đã công bố; count = số tuyến đã công bố (không phải số di sản). */
    private function routeProvinces(LookupService $lookup): array
    {
        $routeCounts = TourRoute::published()->whereNotNull('province_id')
            ->selectRaw('province_id, COUNT(*) as total')->groupBy('province_id')->pluck('total', 'province_id');

        return collect($lookup->provinces())
            ->filter(fn (array $p) => isset($routeCounts[$p['id']]))
            ->map(fn (array $p) => array_merge($p, ['count' => (int) $routeCounts[$p['id']]]))
            ->values()->all();
    }

    public function show(Request $request, TourRoute $route, BookingService $bookings): Response
    {
        abort_unless($route->status === 'published', 404);
        $route->load(RouteDetailResource::eager());

        return Inertia::render('Tours/Show', [
            'route' => RouteDetailResource::one($route),
            'holdMinutes' => $bookings->holdMinutes(),
            'canBook' => $request->user() !== null && (bool) $request->user()->is_active,
            'cancelBeforeHours' => (int) config('disanso.booking.cancel_before_hours', 24),
        ]);
    }
}
