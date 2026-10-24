<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Services\ReportService;
use Inertia\Inertia;
use Inertia\Response;

/** Trang "Báo cáo & xuất Excel": thống kê theo mức/loại/tỉnh/tháng/nguồn; agency chỉ thấy tỉnh mình. */
class ReportController extends Controller
{
    public function index(ReportFilterRequest $request, ReportService $reports): Response
    {
        return Inertia::render('Admin/Reports/Index', $reports->page($request->scope()));
    }
}
