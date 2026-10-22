<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\LookupService;
use App\Services\RiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** KPI công khai (không có số liệu cá nhân) cho bảng tin và tích hợp bên ngoài. */
class StatsController extends ApiController
{
    public function __invoke(Request $request, LookupService $lookup, RiskService $risk): JsonResponse
    {
        $this->applyLocale($request);
        $summary = $risk->nationalSummary();

        return response()->json([
            'data' => $lookup->stats() + [
                'national_level' => $summary['national_level'],
                'national_label' => RiskService::levelLabel($summary['national_level']),
                'alerts_by_severity' => $lookup->alertsBySeverity(),
            ],
            'updated_at' => $summary['updated_at'] ?? now()->toIso8601String(),
        ]);
    }
}
