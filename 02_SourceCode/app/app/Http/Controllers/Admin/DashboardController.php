<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artifact;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Contribution;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\Interpretation;
use App\Models\MediaAsset;
use App\Models\PageView;
use App\Models\SearchLog;
use App\Models\TourRoute;
use App\Models\User;
use App\Services\AnalyticsClient;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AnalyticsClient $analytics): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'kpis' => $this->kpis(),
            'charts' => [
                'viewsByDay' => $this->viewsByDay(),
                'bookingsByStatus' => Booking::query()->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->get()
                    ->map(fn ($r) => ['status' => $r->status, 'label' => __('enums.booking_statuses.'.$r->status), 'count' => (int) $r->count])->all(),
                'sitesByProvince' => HeritageSite::query()->join('provinces', 'provinces.id', '=', 'heritage_sites.province_id')
                    ->whereNull('heritage_sites.deleted_at')
                    ->select(app()->getLocale() === 'en' ? 'provinces.name_en as name' : 'provinces.name as name', DB::raw('COUNT(*) as count'))
                    ->groupBy('name')->orderByDesc('count')->limit(10)->get()
                    ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count])->all(),
                'searchEngines' => SearchLog::query()->select('engine', DB::raw('COUNT(*) as count'))->groupBy('engine')->get()
                    ->map(fn ($r) => ['engine' => $r->engine, 'count' => (int) $r->count])->all(),
            ],
            'recentAudit' => AuditLog::query()->with('user:id,name')->latest('id')->limit(10)->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name,
                    'subject' => $log->subject_type ? $log->subject_type.'#'.$log->subject_id : null,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->all(),
            'analyticsUp' => $analytics->isUp(),
        ]);
    }

    private function kpis(): array
    {
        $sites = HeritageSite::count();
        $published = HeritageSite::published()->count();
        $since = now()->subDays(30);

        return [
            'sites' => $sites,
            'artifacts' => Artifact::count(),
            'figures' => HistoricalFigure::count(),
            'routes' => TourRoute::count(),
            'publishedRatio' => $sites ? round($published / $sites, 3) : 0,
            'bookings30d' => Booking::query()->where('created_at', '>=', $since)->count(),
            'revenue30d' => (int) Booking::query()->where('created_at', '>=', $since)->where('status', Booking::PAID)->sum('total_vnd'),
            'pendingContributions' => Contribution::query()->where('status', 'pending')->count(),
            'users' => User::count(),
            // Tổng bản ghi nghiệp vụ chính (đề yêu cầu ≥ 300)
            'records' => $sites + Artifact::count() + HistoricalFigure::count() + Interpretation::count() + MediaAsset::count(),
        ];
    }

    private function viewsByDay(): array
    {
        $rows = PageView::query()->where('viewed_on', '>=', now()->subDays(29)->toDateString())
            ->select('viewed_on', DB::raw('COUNT(*) as views'))->groupBy('viewed_on')->pluck('views', 'viewed_on');
        $rows = $rows->mapWithKeys(fn ($v, $k) => [substr((string) $k, 0, 10) => (int) $v]);

        return collect(range(29, 0))->map(function ($daysAgo) use ($rows) {
            $date = now()->subDays($daysAgo)->toDateString();

            return ['date' => $date, 'views' => (int) ($rows[$date] ?? 0)];
        })->all();
    }
}
