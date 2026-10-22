<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertMatch;
use App\Models\Trip;
use App\Models\TripLeg;
use App\Models\Zone;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Đối sánh cảnh báo với chặng hành trình (F3). Đường chính: Python POST /match (shapely, ghi alert_matches);
 * Python tắt hoặc lỗi → dự phòng PHP cùng quy tắc:
 *  - cảnh báo có geometry (Polygon/MultiPolygon/Circle) → chỉ xét vị trí chặng nằm trong hình (geometry, distance_km tới tâm Circle);
 *  - không có geometry → vùng trong alert_zones kèm vùng con: chặng thuộc vùng (zone) hoặc điểm đến thuộc vùng (destination);
 *  - chỉ hành trình active, chặng phải giao thời gian với cảnh báo. Idempotent theo unique(alert_id, leg_id), giữ status cũ.
 */
class MatchingService
{
    public function __construct(private readonly AnalyticsClient $analytics, private readonly RiskService $risk)
    {
    }

    /**
     * Đối sánh một cảnh báo với mọi hành trình; trả các đối sánh đang ở trạng thái "new" (chưa xếp thông báo).
     *
     * @return Collection<int, AlertMatch>
     */
    public function matchAlert(Alert $alert): Collection
    {
        if (! $alert->isLive()) {
            return collect();
        }
        $engine = 'python';
        if ($this->analytics->match($alert->id) === null) {
            $engine = 'php';
            $this->upsert($alert, $this->findMatches($alert));
        }
        $tripIds = AlertMatch::query()->where('alert_id', $alert->id)->distinct()->pluck('trip_id');
        $this->risk->refreshTrips($tripIds);
        Log::channel('nghiepvu')->info('Đối sánh cảnh báo', ['alert' => $alert->code, 'engine' => $engine, 'trips' => $tripIds->count()]);

        return AlertMatch::query()->where('alert_id', $alert->id)->where('status', AlertMatch::STATUS_NEW)->get();
    }

    /**
     * Đối sánh một hành trình (mới tạo/vừa sửa) với mọi cảnh báo đang hiệu lực bằng PHP (cục bộ, nhanh, cùng quy tắc);
     * xóa đối sánh không còn đúng sau khi đổi chặng. Trả các đối sánh "new".
     *
     * @return Collection<int, AlertMatch>
     */
    public function matchTrip(Trip $trip): Collection
    {
        $keep = [];
        if ($trip->status === Trip::STATUS_ACTIVE) {
            foreach (Alert::query()->live()->with('zones')->get() as $alert) {
                $found = $this->findMatches($alert, $trip->id);
                $this->upsert($alert, $found);
                foreach ($found as $row) {
                    $keep[] = $alert->id.':'.$row['leg_id'];
                }
            }
        }
        AlertMatch::query()->where('trip_id', $trip->id)->whereIn('status', [AlertMatch::STATUS_NEW, AlertMatch::STATUS_NOTIFIED])
            ->get()->reject(fn (AlertMatch $m) => in_array($m->alert_id.':'.$m->leg_id, $keep, true))
            ->each(fn (AlertMatch $m) => $m->forceFill(['status' => AlertMatch::STATUS_RESOLVED])->save());
        $this->risk->refreshTrip($trip);

        return AlertMatch::query()->where('trip_id', $trip->id)->where('status', AlertMatch::STATUS_NEW)->get();
    }

    /**
     * Tính đối sánh bằng PHP (không ghi CSDL).
     *
     * @return array<int, array{trip_id:int, leg_id:int, match_type:string, distance_km:?float, overlap_from:Carbon, overlap_to:Carbon}>
     */
    public function findMatches(Alert $alert, ?int $tripId = null): array
    {
        $geometry = is_array($alert->geometry) && isset($alert->geometry['type']) ? $alert->geometry : null;
        $zoneIds = $geometry ? [] : $this->withDescendants($alert->zones()->pluck('zones.id')->all());
        if (! $geometry && $zoneIds === []) {
            return [];
        }
        $aStart = $alert->starts_at ?? $alert->issued_at ?? Carbon::create(1970);
        $aEnd = $alert->ends_at ?? Carbon::create(9999, 12, 31);
        $out = [];
        foreach ($this->candidateLegs($aStart, $aEnd, $tripId, $geometry ? null : $zoneIds) as $leg) {
            $window = $this->overlap($leg, $aStart, $aEnd);
            $verdict = $window ? $this->decide($leg, $geometry, $zoneIds) : null;
            if ($verdict) {
                $out[] = ['trip_id' => $leg->trip_id, 'leg_id' => $leg->id, 'match_type' => $verdict[0],
                    'distance_km' => $verdict[1], 'overlap_from' => $window[0], 'overlap_to' => $window[1]];
            }
        }

        return $out;
    }

    /** Ghi đối sánh idempotent theo unique(alert_id, leg_id); không đổi status đã có. */
    private function upsert(Alert $alert, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $now = now();
        AlertMatch::query()->upsert(array_map(fn ($r) => $r + [
            'alert_id' => $alert->id, 'status' => AlertMatch::STATUS_NEW, 'created_at' => $now, 'updated_at' => $now,
        ], $rows), ['alert_id', 'leg_id'], ['trip_id', 'match_type', 'distance_km', 'overlap_from', 'overlap_to', 'updated_at']);
    }

    /** Chặng của hành trình active có thể giao thời gian; lọc sơ bộ theo vùng khi đối sánh theo vùng. */
    private function candidateLegs(Carbon $aStart, Carbon $aEnd, ?int $tripId, ?array $zoneIds): Collection
    {
        return TripLeg::query()->with('trip:id,status,start_date,end_date', 'destination:id,zone_id,lat,lng', 'zone:id,centroid_lat,centroid_lng')
            ->whereHas('trip', fn ($q) => $q->where('status', Trip::STATUS_ACTIVE)->when($tripId, fn ($t) => $t->whereKey($tripId)))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<', $aEnd))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $aStart))
            ->when($zoneIds !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('zone_id', $zoneIds)
                ->orWhereHas('destination', fn ($d) => $d->whereIn('zone_id', $zoneIds))))
            ->get();
    }

    /** Khoảng giao [max(start), min(end)]; chặng thiếu giờ dùng ngày đầu/cuối hành trình. @return array{0: Carbon, 1: Carbon}|null */
    private function overlap(TripLeg $leg, Carbon $aStart, Carbon $aEnd): ?array
    {
        $legStart = $leg->starts_at ?? $leg->trip?->start_date?->copy()->startOfDay() ?? Carbon::create(1970);
        $legEnd = $leg->ends_at ?? $leg->trip?->end_date?->copy()->endOfDay() ?? Carbon::create(9999, 12, 31);
        $start = $legStart->max($aStart);
        $end = $legEnd->min($aEnd);

        return $start->lt($end) ? [$start->copy(), $end->copy()] : null;
    }

    /** @return array{0: string, 1: ?float}|null */
    private function decide(TripLeg $leg, ?array $geometry, array $zoneIds): ?array
    {
        if ($geometry) {
            [$lat, $lng] = $leg->position();
            if ($lat === null || ! Geo::contains($geometry, $lat, $lng)) {
                return null;
            }
            if ($geometry['type'] === 'Circle') {
                [$cLng, $cLat] = Geo::lngLat($geometry['center'] ?? []);

                return ['geometry', round(Geo::haversineKm($cLat, $cLng, $lat, $lng), 1)];
            }

            return ['geometry', 0.0];
        }
        if (in_array((int) $leg->zone_id, $zoneIds, true)) {
            return ['zone', null];
        }
        if ($leg->destination && in_array((int) $leg->destination->zone_id, $zoneIds, true)) {
            return ['destination', null];
        }

        return null;
    }

    /** Thêm mọi vùng con (parent_id) của các vùng đã chọn. */
    public function withDescendants(array $zoneIds): array
    {
        $all = array_map('intval', $zoneIds);
        $frontier = $all;
        while ($frontier !== []) {
            $children = Zone::query()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $frontier = array_values(array_diff($children, $all));
            $all = array_merge($all, $frontier);
        }

        return $all;
    }
}
