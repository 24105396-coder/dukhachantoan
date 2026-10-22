<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/** /healthz cho Docker healthcheck và giám sát: 503 khi CSDL lỗi. */
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
            'db' => $db,
            'analytics' => $analyticsUp ? 'ok' : 'down',
            'version' => config('disanso.version'),
        ], $db === 'ok' ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
