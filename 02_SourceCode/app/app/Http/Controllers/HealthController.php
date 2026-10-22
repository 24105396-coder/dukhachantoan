<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\WeatherObservation;
use App\Services\AnalyticsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/** /healthz cho Docker healthcheck và giám sát: 503 khi CSDL lỗi; kèm tình trạng hàng đợi và lần lấy thời tiết gần nhất. */
class HealthController extends Controller
{
    public function __invoke(AnalyticsClient $analytics): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $db = 'ok';
        } catch (Throwable) {
            $db = 'fail';
        }

        $analyticsUp = false;
        try {
            $analyticsUp = $analytics->isUp();
        } catch (Throwable) {
            // Cache có thể dùng CSDL; khi CSDL lỗi coi như dịch vụ phân tích không rõ trạng thái
        }

        return response()->json([
            'status' => $db === 'ok' ? 'ok' : 'fail',
            'time' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'db' => $db,
            'analytics' => $analyticsUp ? 'ok' : 'down',
            'queue' => $db === 'ok' ? $this->queue() : null,
            'last_weather_fetch' => $db === 'ok' ? $this->lastWeatherFetch() : null,
            'version' => config('dukhach.version'),
        ], $db === 'ok' ? 200 : 503)->header('Cache-Control', 'no-store');
    }

    /** queued/failed: thông báo nghiệp vụ; jobs/failed_jobs: hàng đợi Laravel (database). */
    private function queue(): ?array
    {
        try {
            $counts = Notification::query()
                ->whereIn('status', [Notification::STATUS_QUEUED, Notification::STATUS_FAILED])
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

            return [
                'queued' => (int) ($counts[Notification::STATUS_QUEUED] ?? 0),
                'failed' => (int) ($counts[Notification::STATUS_FAILED] ?? 0),
                'jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function lastWeatherFetch(): ?string
    {
        try {
            $last = WeatherObservation::query()->max('fetched_at');

            return $last ? Carbon::parse($last)->toIso8601String() : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
