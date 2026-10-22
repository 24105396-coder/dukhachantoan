<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\EmergencyContact;
use App\Models\HazardType;
use App\Models\Province;
use App\Models\Trip;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Danh mục tham chiếu đã địa phương hóa (bộ lọc, form) và số liệu tổng quan công khai. */
class LookupService
{
    public const STATS_CACHE_SECONDS = 60;

    /** {zones, alerts_active, trips_active, contacts} – không chứa dữ liệu cá nhân. */
    public function stats(): array
    {
        try {
            return Cache::remember('stats:overview', self::STATS_CACHE_SECONDS, fn () => [
                'zones' => Zone::query()->active()->count(),
                'alerts_active' => Alert::query()->live()->count(),
                'trips_active' => Trip::query()->ongoing()->count(),
                'contacts' => EmergencyContact::query()->count(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return ['zones' => 0, 'alerts_active' => 0, 'trips_active' => 0, 'contacts' => 0];
        }
    }

    /** [{id, code, name, region, lat, lng}] */
    public function provinces(): array
    {
        return Province::query()->orderBy('name')->get()
            ->map(fn (Province $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->localizedName(),
                'region' => $p->region,
                'lat' => (float) $p->lat,
                'lng' => (float) $p->lng,
            ])->values()->all();
    }

    /** [{code, name, icon, color}] theo thứ tự hiển thị. */
    public function hazards(): array
    {
        return HazardType::query()->with('translations')->orderBy('sort_order')->get()
            ->map(fn (HazardType $h) => $h->brief())->values()->all();
    }

    /** [{code, name, province}] cho ô chọn vùng. */
    public function zones(): array
    {
        return Zone::query()->active()->with('province')->orderBy('name')->get()
            ->map(fn (Zone $z) => $z->brief() + ['province' => $z->province?->brief()])->values()->all();
    }

    /** Số cảnh báo đang hiệu lực theo mức {1..4}. */
    public function alertsBySeverity(): array
    {
        try {
            $counts = Alert::query()->live()->selectRaw('severity, COUNT(*) AS total')->groupBy('severity')->pluck('total', 'severity');

            return collect([1, 2, 3, 4])->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all();
        } catch (Throwable $e) {
            report($e);

            return [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        }
    }
}
