<?php

namespace Database\Seeders;

use App\Models\AlertSource;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Nguồn dữ liệu cảnh báo: mặc định 3 nguồn; database/data/alert_sources.json (nếu có) bổ sung/ghi đè theo code. */
class AlertSourceSeeder extends Seeder
{
    use ReadsSeedJson;

    public const DEFAULTS = [
        [
            'code' => 'openmeteo', 'name' => 'Open-Meteo (thời tiết hiện tại và dự báo 7 ngày)', 'source_type' => 'weather', 'provider' => 'openmeteo',
            'base_url' => 'https://api.open-meteo.com/v1/forecast', 'attribution' => 'Weather data by Open-Meteo.com', 'license' => 'CC BY 4.0',
            'poll_interval_min' => 30,
        ],
        [
            'code' => 'manual', 'name' => 'Cơ quan hỗ trợ du khách (nhập tay)', 'source_type' => 'manual', 'provider' => 'manual',
            'base_url' => null, 'attribution' => 'Sở Du lịch / Trung tâm hỗ trợ du khách các tỉnh (mô phỏng)', 'license' => null,
            'poll_interval_min' => null,
        ],
        [
            'code' => 'simulation', 'name' => 'Kịch bản mô phỏng', 'source_type' => 'simulation', 'provider' => 'simulated',
            'base_url' => null, 'attribution' => 'Dữ liệu mô phỏng phục vụ kiểm thử, dựa trên sự kiện lịch sử có nguồn', 'license' => null,
            'poll_interval_min' => null,
        ],
    ];

    public function run(): void
    {
        $extra = $this->hasJson('alert_sources.json') ? $this->readJson('alert_sources.json') : [];
        foreach (array_merge(self::DEFAULTS, $extra) as $row) {
            if (empty($row['code'])) {
                continue;
            }
            AlertSource::updateOrCreate(['code' => $row['code']], collect($row)->only([
                'name', 'source_type', 'provider', 'base_url', 'attribution', 'license', 'poll_interval_min',
            ])->all() + ['is_active' => $row['is_active'] ?? true]);
        }
    }
}
