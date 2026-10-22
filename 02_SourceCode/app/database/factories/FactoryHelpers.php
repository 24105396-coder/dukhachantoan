<?php

namespace Database\Factories;

use App\Models\AlertSource;
use App\Models\HazardType;
use App\Models\Province;

/**
 * Tiện ích dùng chung cho factory: tỉnh, nguồn và loại rủi ro mặc định (tạo nếu chưa có).
 */
final class FactoryHelpers
{
    public static function provinceId(string $code = 'KH'): int
    {
        $defaults = [
            'KH' => ['name' => 'Khánh Hòa', 'name_en' => 'Khanh Hoa', 'region' => 'trung', 'lat' => 12.2388, 'lng' => 109.1967],
            'HN' => ['name' => 'Hà Nội', 'name_en' => 'Hanoi', 'region' => 'bac', 'lat' => 21.0285, 'lng' => 105.8542],
        ];

        return Province::firstOrCreate(['code' => $code], $defaults[$code] ?? [
            'name' => $code, 'name_en' => $code, 'region' => 'bac', 'lat' => 21.0, 'lng' => 105.8,
        ])->id;
    }

    public static function sourceId(string $code = 'manual'): int
    {
        return AlertSource::firstOrCreate(['code' => $code], [
            'name' => 'Cơ quan hỗ trợ (nhập tay)',
            'source_type' => 'manual',
            'provider' => 'manual',
        ])->id;
    }

    public static function hazardTypeId(string $code = 'bao'): int
    {
        $hazard = HazardType::firstOrCreate(['code' => $code], ['icon' => $code, 'color' => '#d32f2f', 'default_severity' => 3]);
        if (! $hazard->translations()->exists()) {
            $hazard->syncTranslations(['vi' => ['name' => ucfirst($code)], 'en' => ['name' => ucfirst($code)]]);
        }

        return $hazard->id;
    }
}
