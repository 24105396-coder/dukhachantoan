<?php

namespace App\Console\Commands;

use App\Services\WeatherService;
use Illuminate\Console\Command;

/** Lấy thời tiết Open-Meteo qua dịch vụ Python, tính điểm rủi ro, sinh cảnh báo nháp (lịch: mỗi 30 phút). */
class FetchWeather extends Command
{
    protected $signature = 'app:fetch-weather {--zone=* : Chỉ lấy các mã vùng này}';
    protected $description = 'Lấy thời tiết Open-Meteo qua dịch vụ Python, tính điểm rủi ro, sinh cảnh báo nháp (lịch: mỗi 30 phút).';

    public function handle(WeatherService $weather): int
    {
        $result = $weather->fetchAll((array) $this->option('zone'));
        $this->line(sprintf('%s app:fetch-weather status=%s fetched=%s risk_zones=%d drafts=%d skipped=%d',
            now()->toDateTimeString(), $result['status'], json_encode($result['fetched']), $result['risk_zones'],
            $result['drafts_created'], $result['drafts_skipped']));

        // Python tắt không phải lỗi của lệnh: dữ liệu cũ được giữ, trạng thái nguồn ghi error
        return self::SUCCESS;
    }
}
