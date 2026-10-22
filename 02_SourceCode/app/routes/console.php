<?php

use Illuminate\Support\Facades\Schedule;

// Lịch chạy nền: giải phóng giữ chỗ hết hạn mỗi phút, sao lưu CSDL hằng ngày lúc 02:00.
Schedule::command('app:expire-holds')->everyMinute()->withoutOverlapping();
// Cộng lượt xem chờ vào view_count theo lô
Schedule::command('app:sync-view-counts')->everyMinute()->withoutOverlapping();
Schedule::command('app:backup-db')->dailyAt('02:00')->appendOutputTo(storage_path('logs/backup.log'));
// Đồng bộ chỉ mục tìm kiếm của dịch vụ Python với nội dung mới (entrypoint cũng gọi ngay sau migrate/seed)
Schedule::command('app:reindex-search')->everyTenMinutes()->withoutOverlapping()->runInBackground();
