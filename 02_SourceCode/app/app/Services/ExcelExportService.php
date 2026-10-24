<?php

namespace App\Services;

use App\Services\Reports\ExportDatasets;
use App\Services\Reports\ExportFile;
use App\Services\Reports\ReportScope;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sinh tệp .xlsx (PhpSpreadsheet): sheet đầu "Meta" (hệ thống, người xuất, phạm vi, số dòng, ghi chú thật/mô phỏng),
 * sau đó sheet dữ liệu: tiêu đề in đậm, đóng băng hàng 1, độ rộng cột theo nội dung, ngày giờ dd/mm/yyyy hh:mm.
 * Chuỗi luôn ghi kiểu text để nội dung người dùng bắt đầu bằng "=" không thành công thức (chống CSV/Excel injection).
 */
class ExcelExportService
{
    public const MAX_ROWS = 20000;
    /** Đủ cho 20 000 dòng × 15 cột (đo thực tế ~210 MB đỉnh). */
    public const MEMORY_LIMIT = '512M';
    public const META_SHEET = 'Meta';
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const CHUNK = 1000;
    private const DATETIME_FORMAT = 'dd/mm/yyyy hh:mm';
    private const DATE_FORMAT = 'dd/mm/yyyy';
    private const DATE_ONLY_COLUMNS = ['start_date', 'end_date', 'verified_at'];
    private const MIN_WIDTH = 8;
    private const MAX_WIDTH = 60;

    public function __construct(
        private readonly ReportService $reports,
        private readonly ExportDatasets $datasets,
        private readonly AuditLogger $audit,
    ) {
    }

    /** Dựng tệp + ghi nhật ký `export.<dataset>`. */
    public function export(string $dataset, ReportScope $scope): ExportFile
    {
        @ini_set('memory_limit', self::MEMORY_LIMIT);
        $file = $dataset === ExportDatasets::SUMMARY ? $this->summaryBook($scope) : $this->datasetBook($dataset, $scope);
        $action = 'export.'.$dataset;
        $this->audit->log($action, null, [
            'rows' => $file->rows, 'total' => $file->total, 'truncated' => $file->truncated(),
            'from' => $scope->from->toDateString(), 'to' => $scope->to->toDateString(),
            'province' => $scope->province?->code, 'filters' => $scope->extra ?: null,
        ], $scope->user);

        return $file;
    }

    public function download(ExportFile $file): StreamedResponse
    {
        return response()->streamDownload(function () use ($file) {
            (new Xlsx($file->spreadsheet))->save('php://output');
            $file->spreadsheet->disconnectWorksheets();
        }, $file->filename, ['Content-Type' => self::MIME, 'Cache-Control' => 'no-store, private']);
    }

    public static function filename(string $dataset): string
    {
        return 'DuKhachAnToan_'.$dataset.'_'.now()->format('Ymd_Hi').'.xlsx';
    }

    private function datasetBook(string $dataset, ReportScope $scope): ExportFile
    {
        $query = $this->datasets->query($dataset, $scope);
        $total = (clone $query)->toBase()->getCountForPagination();
        $rows = (function () use ($query, $dataset) {
            $n = 0;
            foreach ($query->lazy(self::CHUNK) as $model) {
                if (++$n > self::MAX_ROWS) {
                    return;
                }
                yield $this->datasets->row($dataset, $model);
            }
        })();
        $book = $this->newBook($dataset);
        $columns = $this->datasets->columns($dataset);
        $sheet = $book->createSheet()->setTitle($this->sheetTitle($dataset));
        $written = $this->writeTable($sheet, $columns, $rows);
        $this->writeMeta($book->getSheet(0), $dataset, $scope, $written, $total);

        return new ExportFile($book, self::filename($dataset), $written, $total);
    }

    /** Tổng hợp: Meta + Tổng quan + 5 bảng của trang Báo cáo. */
    private function summaryBook(ReportScope $scope): ExportFile
    {
        $book = $this->newBook(ExportDatasets::SUMMARY);
        $summary = $this->reports->summary($scope);
        $overview = array_map(fn ($key) => [__('reports.summary.'.$key), $summary[$key]], array_keys($summary));
        $written = $this->writeTable($book->createSheet()->setTitle($this->sheetTitle('overview')), ['metric', 'value'], $overview);
        foreach ($this->summaryTables($scope) as $sheetKey => [$columns, $rows]) {
            $written += $this->writeTable($book->createSheet()->setTitle($this->sheetTitle($sheetKey)), $columns, $rows);
        }
        $this->writeMeta($book->getSheet(0), ExportDatasets::SUMMARY, $scope, $written, $written);

        return new ExportFile($book, self::filename(ExportDatasets::SUMMARY), $written, $written);
    }

    /** @return array<string, array{0: list<string>, 1: list<array>}> */
    private function summaryTables(ReportScope $scope): array
    {
        $t = $this->reports->tables($scope);
        $kinds = fn ($kind) => __('exports.values.source_kinds.'.$kind);

        return [
            'by_level' => [['level', 'severity_label', 'alerts'], array_map(fn ($r) => [$r['level'], $r['label'], $r['count']], $t['byLevel'])],
            'by_hazard' => [['hazard_code', 'hazard', 'alerts'], array_map(fn ($r) => [$r['code'], $r['name'], $r['count']], $t['byHazard'])],
            'by_province' => [['province', 'alerts', 'trips', 'checkins_need_help'],
                array_map(fn ($r) => [$r['name'], $r['alerts'], $r['trips'], $r['checkins_need_help']], $t['byProvince'])],
            'by_month' => [['month', 'alerts', 'notifications_sent', 'notifications_failed'],
                array_map(fn ($r) => [$r['month'], $r['alerts'], $r['notifications_sent'], $r['notifications_failed']], $t['byMonth'])],
            'by_source' => [['source_name', 'source_kind', 'alerts', 'last_fetched_at'], array_map(fn ($r) => [
                $r['name'], $kinds($r['kind']), $r['alerts'], $r['last_fetched_at'] ? Carbon::parse($r['last_fetched_at']) : null,
            ], $t['bySource'])],
        ];
    }

    private function newBook(string $dataset): Spreadsheet
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('DuKhachAnToan')->setTitle('DuKhachAnToan – '.__('exports.datasets.'.$dataset));
        $book->getActiveSheet()->setTitle(self::META_SHEET);

        return $book;
    }

    private function writeMeta(Worksheet $sheet, string $dataset, ReportScope $scope, int $rows, int $total): void
    {
        $user = $scope->user->loadMissing('role');
        $rowsText = $total > $rows
            ? __('exports.meta.rows_truncated', ['rows' => $rows, 'total' => $total, 'max' => number_format(self::MAX_ROWS, 0, ',', '.')])
            : (string) $rows;
        $pairs = [
            [__('exports.meta.system'), __('exports.meta.system_name')],
            [__('exports.meta.dataset'), __('exports.datasets.'.$dataset)],
            [__('exports.meta.exported_at'), now()],
            [__('exports.meta.exported_by'), $user->name.' ('.__('enums.roles.'.$user->role?->code).')'],
            [__('exports.meta.province'), $scope->province?->localizedName() ?? __('exports.values.national')],
            [__('exports.meta.period'), $scope->from->format('d/m/Y').' – '.$scope->to->format('d/m/Y')],
            [__('exports.meta.rows'), $rowsText],
            [__('exports.meta.filters'), $scope->extra ? json_encode($scope->extra, JSON_UNESCAPED_UNICODE) : '—'],
            [__('exports.meta.note'), __('exports.meta.data_note')],
        ];
        $this->writeTable($sheet, ['field', 'value'], $pairs);
    }

    /** Ghi tiêu đề (dịch) + các dòng; trả số dòng dữ liệu. */
    private function writeTable(Worksheet $sheet, array $columns, iterable $rows): int
    {
        $widths = [];
        foreach ($columns as $i => $column) {
            $label = __('exports.columns.'.$column);
            $sheet->setCellValueExplicit([$i + 1, 1], $label, DataType::TYPE_STRING);
            $widths[$i] = mb_strlen($label);
        }
        $r = 1;
        foreach ($rows as $row) {
            $r++;
            foreach (array_values($row) as $i => $value) {
                $widths[$i] = max($widths[$i] ?? 0, $this->writeCell($sheet, $i + 1, $r, $value, $columns[$i] ?? ''));
            }
        }
        $this->finishSheet($sheet, count($columns), $widths);

        return $r - 1;
    }

    /** Ghi một ô theo kiểu; trả độ dài hiển thị ước lượng để tính độ rộng cột. */
    private function writeCell(Worksheet $sheet, int $col, int $row, mixed $value, string $column): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if ($value instanceof DateTimeInterface) {
            $local = Carbon::instance($value)->setTimezone(config('app.timezone'));
            $dateOnly = in_array($column, self::DATE_ONLY_COLUMNS, true);
            $sheet->setCellValue([$col, $row], ExcelDate::PHPToExcel($local));
            $sheet->getStyle([$col, $row])->getNumberFormat()->setFormatCode($dateOnly ? self::DATE_FORMAT : self::DATETIME_FORMAT);

            return $dateOnly ? 10 : 16;
        }
        if (is_int($value) || is_float($value)) {
            $sheet->setCellValueExplicit([$col, $row], $value, DataType::TYPE_NUMERIC);

            return strlen((string) $value);
        }
        $text = (string) $value;
        $sheet->setCellValueExplicit([$col, $row], $text, DataType::TYPE_STRING);

        return mb_strlen($text);
    }

    private function finishSheet(Worksheet $sheet, int $columnCount, array $widths): void
    {
        $last = Coordinate::stringFromColumnIndex(max(1, $columnCount));
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range(1, max(1, $columnCount)) as $i) {
            $width = min(self::MAX_WIDTH, max(self::MIN_WIDTH, ($widths[$i - 1] ?? 0) + 2));
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth($width);
        }
    }

    /** Tên sheet đã dịch (Excel giới hạn 31 ký tự, cấm : \ / ? * [ ]). */
    private function sheetTitle(string $key): string
    {
        return mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', __('exports.sheets.'.$key)), 0, 31);
    }
}
