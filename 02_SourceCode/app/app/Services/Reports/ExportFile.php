<?php

namespace App\Services\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Kết quả dựng tệp Excel: workbook, tên tệp, số dòng dữ liệu đã ghi, tổng số dòng nguồn, có bị cắt không. */
final class ExportFile
{
    public function __construct(
        public readonly Spreadsheet $spreadsheet,
        public readonly string $filename,
        public readonly int $rows,
        public readonly int $total,
    ) {
    }

    public function truncated(): bool
    {
        return $this->total > $this->rows;
    }
}
