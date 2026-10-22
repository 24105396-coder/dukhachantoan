<?php

namespace Database\Seeders;

use App\Models\HazardType;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/**
 * Loại rủi ro: ưu tiên database/data/hazard_types.json (dka-data-author); chưa có thì dùng danh sách tạm theo data-model.
 */
class HazardTypeSeeder extends Seeder
{
    use ReadsSeedJson;

    /** code => [icon, màu, mức mặc định, vi, en] */
    public const FALLBACK = [
        'bao' => ['cyclone', '#b71c1c', 4, 'Bão', 'Typhoon'],
        'ap_thap' => ['storm', '#d84315', 3, 'Áp thấp nhiệt đới', 'Tropical depression'],
        'mua_lon' => ['rainy', '#1565c0', 3, 'Mưa lớn', 'Heavy rain'],
        'lu' => ['flood', '#0d47a1', 3, 'Lũ', 'Flood'],
        'lu_quet' => ['tsunami', '#4a148c', 4, 'Lũ quét', 'Flash flood'],
        'sat_lo' => ['landslide', '#6d4c41', 4, 'Sạt lở đất', 'Landslide'],
        'song_lon' => ['waves', '#00838f', 3, 'Sóng lớn', 'High waves'],
        'trieu_cuong' => ['water', '#006064', 2, 'Triều cường', 'Storm surge / king tide'],
        'nang_nong' => ['sunny', '#e65100', 2, 'Nắng nóng', 'Heatwave'],
        'ret_dam' => ['ac_unit', '#455a64', 2, 'Rét đậm, rét hại', 'Severe cold'],
        'suong_mu' => ['foggy', '#78909c', 1, 'Sương mù', 'Dense fog'],
        'dong_set' => ['thunderstorm', '#5e35b1', 2, 'Dông, sét', 'Thunderstorm and lightning'],
        'chay_rung' => ['local_fire_department', '#bf360c', 3, 'Cháy rừng', 'Forest fire'],
        'dich_benh' => ['coronavirus', '#2e7d32', 2, 'Dịch bệnh', 'Disease outbreak'],
        'an_ninh' => ['shield', '#37474f', 3, 'An ninh', 'Security incident'],
        'giao_thong' => ['traffic', '#f9a825', 2, 'Giao thông', 'Traffic disruption'],
        'dong_dat' => ['earthquake', '#880e4f', 3, 'Động đất', 'Earthquake'],
        'khac' => ['warning', '#616161', 1, 'Khác', 'Other'],
    ];

    public function run(): void
    {
        $rows = $this->hasJson('hazard_types.json') ? $this->readJson('hazard_types.json') : $this->fallbackRows();
        foreach ($rows as $i => $row) {
            if (empty($row['code'])) {
                continue;
            }
            $hazard = HazardType::updateOrCreate(['code' => $row['code']], [
                'icon' => $row['icon'] ?? null,
                'color' => $row['color'] ?? null,
                'default_severity' => (int) ($row['default_severity'] ?? 2),
                'sort_order' => (int) ($row['sort_order'] ?? $i + 1),
            ]);
            $translations = array_filter(
                $this->translationsFrom($row, ['name' => 'name', 'description' => 'description']),
                fn ($t) => ! empty($t['name']),
            );
            $hazard->syncTranslations($translations);
        }
    }

    private function fallbackRows(): array
    {
        $this->warnSeed('chưa có hazard_types.json, dùng danh sách tạm '.count(self::FALLBACK).' loại');
        $rows = [];
        $order = 1;
        foreach (self::FALLBACK as $code => [$icon, $color, $severity, $vi, $en]) {
            $rows[] = [
                'code' => $code, 'icon' => $icon, 'color' => $color, 'default_severity' => $severity, 'sort_order' => $order++,
                'name' => ['vi' => $vi, 'en' => $en],
            ];
        }

        return $rows;
    }
}
