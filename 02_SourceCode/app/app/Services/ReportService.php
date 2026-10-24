<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertSource;
use App\Models\HazardType;
use App\Models\Notification;
use App\Models\Province;
use App\Models\SafetyCheckin;
use App\Services\Reports\ExportDatasets;
use App\Services\Reports\ReportQueries;
use App\Services\Reports\ReportScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Thống kê trang "Báo cáo & xuất Excel" (admin/agency) theo kỳ và tỉnh. Mỗi bảng là một vài truy vấn GROUP BY,
 * không lặp truy vấn theo từng bản ghi. Số liệu dùng chung với sheet "tong-hop" của tệp Excel.
 */
class ReportService
{
    public const TOP_PROVINCES = 15;
    /** Trạng thái tính là gửi thành công. */
    private const SENT_STATUSES = [Notification::STATUS_SENT, Notification::STATUS_READ];

    public function __construct(
        private readonly ReportQueries $queries,
        private readonly ExportDatasets $datasets,
        private readonly LookupService $lookup,
    ) {
    }

    /** Props đầy đủ cho Inertia Admin/Reports/Index. */
    public function page(ReportScope $scope): array
    {
        $tables = $this->tables($scope);

        return [
            'filters' => ['from' => $scope->from->toDateString(), 'to' => $scope->to->toDateString(), 'province' => $scope->provinceId()],
            'summary' => $this->summary($scope),
            ...$tables,
            'datasets' => $this->datasetLinks($scope, $tables),
            'provinces' => $scope->user->isAdmin()
                ? array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name']], $this->lookup->provinces()) : [],
            'generated_at' => now()->toIso8601String(),
            'scope' => ['province' => $scope->province?->brief()],
        ];
    }

    /** Năm bảng thống kê {byLevel, byHazard, byProvince, byMonth, bySource}. */
    public function tables(ReportScope $scope): array
    {
        return [
            'byLevel' => $this->byLevel($scope),
            'byHazard' => $this->byHazard($scope),
            'byProvince' => $this->byProvince($scope),
            'byMonth' => $this->byMonth($scope),
            'bySource' => $this->bySource($scope),
        ];
    }

    public function summary(ReportScope $scope): array
    {
        $alerts = $this->queries->alerts($scope)->selectRaw('is_simulated, COUNT(*) AS total')->groupBy('is_simulated')
            ->pluck('total', 'is_simulated');
        $notifications = $this->queries->notifications($scope)->selectRaw('status, COUNT(*) AS total')->groupBy('status')
            ->pluck('total', 'status');
        $sent = collect(self::SENT_STATUSES)->sum(fn ($s) => (int) ($notifications[$s] ?? 0));
        $failed = (int) ($notifications[Notification::STATUS_FAILED] ?? 0);

        return [
            'alerts_live' => $this->queries->liveAlerts($scope)->count(),
            'alerts_total' => (int) $alerts->sum(),
            'trips_total' => $this->queries->trips($scope)->count(),
            'trips_affected' => $this->queries->trips($scope)->whereHas('matches')->count(),
            'notifications_sent' => $sent,
            'notifications_failed' => $failed,
            'delivery_rate_pct' => $sent + $failed > 0 ? round($sent * 100 / ($sent + $failed), 1) : null,
            'checkins_need_help_open' => $this->queries->checkins($scope)->where('status', SafetyCheckin::STATUS_NEED_HELP)
                ->where('response_status', '!=', 'closed')->count(),
            'incidents_pending' => $this->queries->incidents($scope)->where('status', 'pending')->count(),
            'contacts_verified' => $this->queries->contacts($scope)->where('is_verified', true)->count(),
            'real_alerts' => (int) ($alerts[0] ?? 0),
            'simulated_alerts' => (int) ($alerts[1] ?? 0),
        ];
    }

    /** @return list<array{level:int, label:string, count:int}> */
    public function byLevel(ReportScope $scope): array
    {
        $counts = $this->queries->alerts($scope)->selectRaw('severity, COUNT(*) AS total')->groupBy('severity')->pluck('total', 'severity');

        return array_map(fn (int $level) => [
            'level' => $level, 'label' => Alert::severityLabel($level), 'count' => (int) ($counts[$level] ?? 0),
        ], array_keys(Alert::SEVERITIES));
    }

    /** @return list<array{code:string, name:string, count:int}> (nhiều trước) */
    public function byHazard(ReportScope $scope): array
    {
        $counts = $this->queries->alerts($scope)->selectRaw('hazard_type_id, COUNT(*) AS total')->groupBy('hazard_type_id')
            ->pluck('total', 'hazard_type_id');
        $hazards = HazardType::query()->with('translations')->whereIn('id', $counts->keys())->get()->keyBy('id');

        return $counts->map(fn ($total, $id) => [
            'code' => $hazards->get($id)?->code, 'name' => $hazards->get($id)?->name, 'count' => (int) $total,
        ])->filter(fn ($row) => $row['code'] !== null)->sortByDesc('count')->values()->all();
    }

    /** Top 15 tỉnh theo số cảnh báo: {province_id, name, alerts, trips, checkins_need_help}. */
    public function byProvince(ReportScope $scope): array
    {
        $alerts = $this->provinceCounts($scope, 'alerts');
        $trips = $this->provinceCounts($scope, 'trips');
        $needHelp = $this->provinceCounts($scope, 'checkins');
        $ids = $alerts->keys()->merge($trips->keys())->merge($needHelp->keys())->unique();
        $names = Province::query()->whereIn('id', $ids)->get()->mapWithKeys(fn (Province $p) => [$p->id => $p->localizedName()]);

        return $ids->map(fn ($id) => [
            'province_id' => (int) $id,
            'name' => $names[$id] ?? (string) $id,
            'alerts' => (int) ($alerts[$id] ?? 0),
            'trips' => (int) ($trips[$id] ?? 0),
            'checkins_need_help' => (int) ($needHelp[$id] ?? 0),
        ])->sortBy([['alerts', 'desc'], ['trips', 'desc'], ['name', 'asc']])->take(self::TOP_PROVINCES)->values()->all();
    }

    /** Mỗi tháng trong kỳ (kể cả tháng 0): {month YYYY-MM, alerts, notifications_sent, notifications_failed}. */
    public function byMonth(ReportScope $scope): array
    {
        $alerts = $this->queries->alerts($scope)
            ->selectRaw("DATE_FORMAT(COALESCE(alerts.issued_at, alerts.created_at), '%Y-%m') AS ym, COUNT(*) AS total")
            ->groupBy('ym')->pluck('total', 'ym');
        $notifications = $this->queries->notifications($scope)
            ->selectRaw("DATE_FORMAT(notifications.created_at, '%Y-%m') AS ym, SUM(CASE WHEN status IN ('sent', 'read') THEN 1 ELSE 0 END) AS sent, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed")
            ->groupBy('ym')->get()->keyBy('ym');

        $months = [];
        for ($m = $scope->from->startOfMonth(); $m->lte($scope->to); $m = $m->addMonth()) {
            $key = $m->format('Y-m');
            $months[] = [
                'month' => $key,
                'alerts' => (int) ($alerts[$key] ?? 0),
                'notifications_sent' => (int) ($notifications[$key]->sent ?? 0),
                'notifications_failed' => (int) ($notifications[$key]->failed ?? 0),
            ];
        }

        return $months;
    }

    /** Mọi nguồn dữ liệu kèm số cảnh báo trong kỳ: {source_id, name, kind, alerts, last_fetched_at}. */
    public function bySource(ReportScope $scope): array
    {
        $counts = $this->queries->alerts($scope)->selectRaw('source_id, COUNT(*) AS total')->groupBy('source_id')->pluck('total', 'source_id');

        return AlertSource::query()->orderBy('id')->get()->map(fn (AlertSource $s) => [
            'source_id' => $s->id,
            'name' => $s->name,
            'kind' => match ($s->provider) {
                'openmeteo' => 'open_meteo',
                'simulated' => 'simulated',
                default => 'manual',
            },
            'alerts' => (int) ($counts[$s->id] ?? 0),
            'last_fetched_at' => $s->last_polled_at?->toIso8601String(),
        ])->values()->all();
    }

    /** Số dòng mỗi bộ dữ liệu + URL tải kèm bộ lọc hiện tại. */
    public function datasetLinks(ReportScope $scope, array $tables): array
    {
        return array_map(fn (string $key) => [
            'key' => $key,
            'label' => __('exports.datasets.'.$key),
            'count' => $key === ExportDatasets::SUMMARY
                ? collect($tables)->sum(fn ($rows) => count($rows))
                : $this->datasets->query($key, $scope)->toBase()->getCountForPagination(),
            'url' => localized_route('admin.exports.download', ['dataset' => $key] + $scope->query()),
        ], ExportDatasets::allowedFor($scope->user));
    }

    /** Đếm DISTINCT theo tỉnh của vùng: cảnh báo (alert_zones), hành trình (trip_legs), check-in cần hỗ trợ. */
    private function provinceCounts(ReportScope $scope, string $kind): Collection
    {
        $query = match ($kind) {
            'alerts' => DB::table('alert_zones')->join('alerts', 'alerts.id', '=', 'alert_zones.alert_id')
                ->join('zones', 'zones.id', '=', 'alert_zones.zone_id')
                ->whereRaw('COALESCE(alerts.issued_at, alerts.created_at) BETWEEN ? AND ?', $scope->range())
                ->selectRaw('zones.province_id AS pid, COUNT(DISTINCT alerts.id) AS total'),
            'trips' => DB::table('trip_legs')->join('trips', 'trips.id', '=', 'trip_legs.trip_id')
                ->join('zones', 'zones.id', '=', 'trip_legs.zone_id')
                ->whereDate('trips.start_date', '<=', $scope->to->toDateString())
                ->whereDate('trips.end_date', '>=', $scope->from->toDateString())
                ->selectRaw('zones.province_id AS pid, COUNT(DISTINCT trips.id) AS total'),
            'checkins' => DB::table('safety_checkins')->join('trip_legs', 'trip_legs.trip_id', '=', 'safety_checkins.trip_id')
                ->join('zones', 'zones.id', '=', 'trip_legs.zone_id')
                ->where('safety_checkins.status', SafetyCheckin::STATUS_NEED_HELP)
                ->whereBetween('safety_checkins.checked_at', $scope->range())
                ->selectRaw('zones.province_id AS pid, COUNT(DISTINCT safety_checkins.id) AS total'),
        };

        return $query->whereNotNull('zones.province_id')
            ->when($scope->provinceId(), fn ($q, $p) => $q->where('zones.province_id', $p))
            ->groupBy('zones.province_id')->pluck('total', 'pid');
    }
}
