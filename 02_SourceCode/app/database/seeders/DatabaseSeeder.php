<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Thứ tự phụ thuộc: vai trò → tỉnh → người dùng → loại rủi ro, nguồn → vùng → điểm đến, danh bạ, hướng dẫn,
 * kịch bản → cảnh báo → hành trình. Seeder đọc JSON thiếu tệp thì bỏ qua kèm cảnh báo; chạy lại an toàn.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ProvinceSeeder::class,
            UserSeeder::class,
            HazardTypeSeeder::class,
            AlertSourceSeeder::class,
            ZoneSeeder::class,
            DestinationSeeder::class,
            ContactSeeder::class,
            GuideSeeder::class,
            ScenarioSeeder::class,
            AlertSeeder::class,
            TripSeeder::class,
        ]);
    }
}
