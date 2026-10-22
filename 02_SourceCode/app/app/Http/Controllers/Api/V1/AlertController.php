<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AlertCard;
use App\Http\Resources\AlertDetail;
use App\Models\Alert;
use App\Services\GeoJsonBuilder;
use App\Services\Queries\AlertSearch;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API cảnh báo: danh sách (lọc mức, tỉnh, đang hiệu lực), chi tiết, GeoJSON cảnh báo đang hiệu lực. */
class AlertController extends ApiController
{
    public function index(Request $request, AlertSearch $search): JsonResponse
    {
        $this->applyLocale($request);
        $filters = $this->filters($request, ['severity', 'province', 'hazard', 'q', 'simulated']);
        $filters['status'] = $request->query('active', '1') === '0' ? 'all' : 'active';

        return $this->paginated($search->query($filters)->paginate($this->perPage($request))->withQueryString(), AlertCard::class);
    }

    public function show(Request $request, string $alert): JsonResponse
    {
        $this->applyLocale($request);
        $model = Alert::query()->where('code', $alert)->where('status', '!=', Alert::STATUS_DRAFT)->with(AlertCard::RELATIONS)->firstOrFail();

        return response()->json(['data' => AlertDetail::one($model)]);
    }

    /** FeatureCollection: cảnh báo có hình học dùng hình đó (Circle → Point + radius_km), không có thì từng vùng ảnh hưởng. */
    public function geojson(Request $request, GeoJsonBuilder $geo): JsonResponse
    {
        $this->applyLocale($request);
        $features = [];
        foreach (Alert::query()->live()->with(AlertCard::RELATIONS)->mostSevere()->get() as $alert) {
            $properties = [
                'code' => $alert->code,
                'severity' => (int) $alert->severity,
                'severity_label' => Alert::severityLabel((int) $alert->severity),
                'hazard' => $alert->hazardType?->code,
                'headline' => $alert->headline,
                'is_simulated' => (bool) $alert->is_simulated,
                'source_name' => $alert->source_name,
                'updated_at_source' => ($alert->updated_at_source ?? $alert->updated_at)?->toIso8601String(),
                'url' => localized_route('alerts.show', ['alert' => $alert->code]),
            ];
            foreach ($this->geometries($alert) as $geometry) {
                $features[] = $geo->feature($geometry['geometry'], $properties + $geometry['extra']);
            }
        }

        return response()->json(['type' => 'FeatureCollection', 'features' => array_values(array_filter($features))])
            ->header('Content-Type', 'application/geo+json');
    }

    private function geometries(Alert $alert): array
    {
        $g = $alert->geometry;
        if (is_array($g) && ($g['type'] ?? null) === 'Circle') {
            return [['geometry' => ['type' => 'Point', 'coordinates' => Geo::lngLat($g['center'] ?? [])], 'extra' => ['radius_km' => (float) ($g['radius_km'] ?? 0)]]];
        }
        if (is_array($g) && isset($g['type'])) {
            return [['geometry' => $g, 'extra' => []]];
        }

        return $alert->zones->map(fn ($zone) => [
            'geometry' => $zone->geometry ?: GeoJsonBuilder::point((float) $zone->centroid_lat, (float) $zone->centroid_lng),
            'extra' => ['zone' => $zone->code, 'radius_km' => $zone->geometry ? null : (float) $zone->radius_km],
        ])->all();
    }
}
