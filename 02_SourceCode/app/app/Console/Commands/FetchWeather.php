<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Lấy thời tiết Open-Meteo qua dịch vụ Python, tính điểm rủi ro, sinh cảnh báo nháp (lịch: mỗi 30 phút).
 * Pha 1: lệnh khung để scheduler chạy không lỗi; nghiệp vụ hiện thực ở pha 2.
 */
class FetchWeather extends Command
{
    protected $signature = 'app:fetch-weather';
    protected $description = 'Lấy thời tiết Open-Meteo qua dịch vụ Python, tính điểm rủi ro, sinh cảnh báo nháp (lịch: mỗi 30 phút).';

    public function handle(): int
    {
        $this->line(now()->toDateTimeString().' app:fetch-weather: chưa hiện thực (pha 2).');

        return self::SUCCESS;
    }
}
