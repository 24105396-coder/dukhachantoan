<?php

use Illuminate\Support\Facades\Schedule;

// Lịch chạy nền (scheduler chạy bằng schedule:work trong container, xem docker/entrypoint.sh).
// Thời tiết + điểm rủi ro + cảnh báo nháp từ ngưỡng: mỗi 30 phút
Schedule::command('app:fetch-weather')->everyThirtyMinutes()->withoutOverlapping()->runInBackground()
    ->appendOutputTo(storage_path('logs/weather.log'));
// Đối sánh cảnh báo hiệu lực với hành trình mới/chưa đối sánh
Schedule::command('app:match-alerts')->everyFiveMinutes()->withoutOverlapping();
// Cảnh báo quá ends_at → expired
Schedule::command('app:expire-alerts')->everyTenMinutes()->withoutOverlapping();
// Gửi thông báo đến hạn, thử lại lũy tiến (1, 5, 15, 60, 240 phút), nhắc lại mức 4
Schedule::command('app:dispatch-notifications')->everyMinute()->withoutOverlapping();
// Sao lưu CSDL hằng ngày lúc 02:00, giữ 7 bản
Schedule::command('app:backup-db')->dailyAt('02:00')->appendOutputTo(storage_path('logs/backup.log'));
