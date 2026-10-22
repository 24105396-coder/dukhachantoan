<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\GeoJsonBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bảng điều khiển cơ quan hỗ trợ (F9). Agency chỉ thấy số liệu tỉnh mình (users.province_id).
 * Pha 1: KPI tính thật từ CSDL; danh sách thẻ (affectedTrips, needHelp, recentAlerts) điền ở pha 2 khi có Resource.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $provinceId = $request->user()->scopedProvinceId();

        return Inertia::render('Admin/Dashboard', [
            'kpis' => $dashboard->kpis($provinceId),
            'affectedTrips' => [],
            'needHelp' => [],
            'recentAlerts' => [],
            'zonesGeo' => GeoJsonBuilder::empty(),
            'queueHealth' => $dashboard->queueHealth(),
            'scope' => ['province' => $request->user()->isAgency() ? $request->user()->province?->brief() : null],
        ]);
    }
}
