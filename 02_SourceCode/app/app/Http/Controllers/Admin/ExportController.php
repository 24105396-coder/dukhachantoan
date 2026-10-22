<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Xuất CSV (UTF-8 có BOM để Excel đọc đúng tiếng Việt). */
class ExportController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): StreamedResponse
    {
        $type = $request->query('type') === 'views' ? 'views' : 'bookings';
        $filters = $this->filters($request, ['status', 'route', 'from', 'to', 'q']);
        $audit->log('admin.export', null, ['type' => $type]);

        $filename = 'disanso_'.$type.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($type, $filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $type === 'views' ? $this->views($out) : $this->bookings($out, $filters);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function bookings($out, array $filters): void
    {
        fputcsv($out, ['code', 'status', 'route', 'starts_at', 'quantity', 'total_vnd', 'contact_name', 'contact_email', 'created_at']);
        BookingController::query($filters)->with(['slot.route' => fn ($q) => $q->withTrashed(), 'slot.route.translations'])
            ->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $b) {
                    fputcsv($out, [
                        self::safe($b->code), self::safe($b->status), self::safe($b->slot?->route?->name),
                        $b->slot?->starts_at?->toIso8601String(), (int) $b->quantity, (int) $b->total_vnd,
                        self::safe($b->contact_name), self::safe($b->contact_email), $b->created_at?->toIso8601String(),
                    ]);
                }
            });
    }

    private function views($out): void
    {
        fputcsv($out, ['date', 'subject_type', 'subject_id', 'locale', 'views']);
        PageView::query()->select('viewed_on', 'subject_type', 'subject_id', 'locale', DB::raw('COUNT(*) as views'))
            ->where('viewed_on', '>=', now()->subDays(90)->toDateString())
            ->groupBy('viewed_on', 'subject_type', 'subject_id', 'locale')->orderBy('viewed_on')->get()
            ->each(fn ($r) => fputcsv($out, [
                substr((string) $r->viewed_on, 0, 10), self::safe($r->subject_type), (int) $r->subject_id, self::safe($r->locale), (int) $r->views,
            ]));
    }

    /**
     * Chống CSV/formula injection cho mọi ô chuỗi: ô bắt đầu (sau khoảng trắng) bằng
     * = + - @ | % hoặc tab/CR/LF được thêm dấu nháy đơn để Excel coi là văn bản.
     */
    public static function safe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\s\x{00A0}]*[=+\-@|%]|^[\t\r\n]/u', $value) ? "'".$value : $value;
    }
}
