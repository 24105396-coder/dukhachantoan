<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsClient;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/** Báo cáo chất lượng dữ liệu do dịch vụ Python sinh ra, lưu cache 10 phút. */
class QualityController extends Controller
{
    public const CACHE_KEY = 'quality:report';
    public const ERROR_KEY = 'quality:last_error';

    public function index(AnalyticsClient $analytics): Response
    {
        return Inertia::render('Admin/DataQuality', [
            'report' => Cache::get(self::CACHE_KEY),
            'analyticsUp' => $analytics->isUp(),
            'lastError' => Cache::get(self::ERROR_KEY),
        ]);
    }

    public function run(AnalyticsClient $analytics, AuditLogger $audit): RedirectResponse
    {
        // Xóa trạng thái "down" đã nhớ để người quản trị luôn thử gọi lại được
        Cache::forget(AnalyticsClient::STATUS_KEY);
        $report = $analytics->qualityReport();
        $minutes = (int) config('disanso.quality_cache_minutes', 10);

        if ($report === null) {
            Cache::put(self::ERROR_KEY, __('messages.admin.quality_failed'), now()->addMinutes($minutes));

            return back()->with('error', __('messages.admin.quality_failed'));
        }

        $report = [
            'generated_at' => $report['generated_at'] ?? now()->toIso8601String(),
            'summary' => [
                'errors' => (int) ($report['summary']['errors'] ?? 0),
                'warnings' => (int) ($report['summary']['warnings'] ?? 0),
                'checks' => (int) ($report['summary']['checks'] ?? count($report['checks'])),
            ],
            'checks' => array_values($report['checks']),
        ];
        Cache::put(self::CACHE_KEY, $report, now()->addMinutes($minutes));
        Cache::forget(self::ERROR_KEY);
        $audit->log('admin.quality.run', null, $report['summary']);

        return back()->with('success', __('messages.admin.quality_done'));
    }
}
