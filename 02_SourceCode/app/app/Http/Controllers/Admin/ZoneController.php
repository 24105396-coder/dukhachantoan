<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZoneRequest;
use App\Http\Resources\ForecastDay;
use App\Http\Resources\WeatherNow;
use App\Http\Resources\ZoneCard;
use App\Models\Zone;
use App\Services\AuditLogger;
use App\Services\RiskService;
use App\Services\WeatherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Vùng rủi ro: sửa tên, hình học (dán GeoJSON), bán kính; xem thời tiết/điểm rủi ro. */
class ZoneController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request, ['type', 'q']);
        $zones = Zone::query()->with('province')
            ->when($request->user()->scopedProvinceId(), fn ($q, $p) => $q->where('province_id', $p))
            ->when($filters['type'], fn ($q, $v) => $q->where('zone_type', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', like_contains($v))->orWhere('code', 'like', like_contains($v))))
            ->orderBy('zone_type')->orderBy('name')->get();

        return Inertia::render('Admin/Zones/Index', [
            'zones' => $zones->map(fn (Zone $z) => ZoneCard::one($z) + ['has_geometry' => is_array($z->geometry), 'is_active' => (bool) $z->is_active])->all(),
            'filters' => $filters,
            'types' => $this->enumOptions('zone_types', Zone::TYPES),
        ]);
    }

    public function edit(Request $request, WeatherService $weather, Zone $zone): Response
    {
        $this->authorizeZone($request, $zone);
        $zone->load('province');

        return Inertia::render('Admin/Zones/Form', [
            'zone' => ZoneCard::one($zone) + [
                'name_vi' => $zone->name,
                'name_en' => $zone->name_en,
                'geometry' => $zone->geometry,
                'population_note' => $zone->population_note,
                'source_name' => $zone->source_name,
                'source_url' => $zone->source_url,
                'is_active' => (bool) $zone->is_active,
            ],
            'now' => WeatherNow::one($weather->now($zone)),
            'forecast' => ForecastDay::list($weather->forecast($zone)),
        ]);
    }

    public function update(ZoneRequest $request, AuditLogger $audit, RiskService $risk, Zone $zone): RedirectResponse
    {
        $this->authorizeZone($request, $zone);
        $zone->fill($request->validated())->save();
        $risk->forgetSummary();
        $audit->log('admin.zone.update', $zone, ['fields' => array_keys($request->validated())]);

        return redirect(localized_route('admin.zones.index'))->with('success', __('messages.admin.updated'));
    }

    private function authorizeZone(Request $request, Zone $zone): void
    {
        $province = $request->user()->scopedProvinceId();
        abort_if($province && (int) $zone->province_id !== $province, 403);
    }
}
