<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Services\ExcelExportService;
use App\Services\Reports\ExportDatasets;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Tải tệp .xlsx của một bộ dữ liệu (8 bộ); nhật ký kiểm toán chỉ admin; agency bị khóa vào tỉnh mình. */
class ExportController extends Controller
{
    public function download(ReportFilterRequest $request, ExcelExportService $exports, string $dataset): StreamedResponse
    {
        abort_unless(in_array($dataset, ExportDatasets::KEYS, true), 404);
        if (in_array($dataset, ExportDatasets::ADMIN_ONLY, true)) {
            $this->authorize('view-audit');
        }

        return $exports->download($exports->export($dataset, $request->scope()));
    }
}
