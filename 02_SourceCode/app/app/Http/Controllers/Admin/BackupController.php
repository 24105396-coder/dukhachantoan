<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

/** Sao lưu CSDL (chỉ admin): xem danh sách bản sao lưu và chạy sao lưu ngay. Không cho tải tệp qua web. */
class BackupController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manage-backup');
        $files = glob(storage_path('app/backups/dukhach_*.sql')) ?: [];
        rsort($files);

        return Inertia::render('Admin/Backup/Index', [
            'backups' => array_map(fn (string $f) => [
                'name' => basename($f),
                'size_kb' => round(filesize($f) / 1024, 1),
                'created_at' => Carbon::createFromTimestamp(filemtime($f))->toIso8601String(),
            ], $files),
            'schedule' => '02:00',
        ]);
    }

    public function store(AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage-backup');
        $code = Artisan::call('app:backup-db');
        $audit->log('admin.backup.run', null, ['exit_code' => $code]);

        return back()->with($code === 0 ? 'success' : 'error', __($code === 0 ? 'messages.admin.backup_ok' : 'messages.admin.backup_failed'));
    }
}
