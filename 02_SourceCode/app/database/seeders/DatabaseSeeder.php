<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ProvinceSeeder::class,
            PeriodSeeder::class,
            TagSeeder::class,
            FigureSeeder::class,
            SiteSeeder::class,
            ArtifactSeeder::class,
            RouteSeeder::class,
            MediaSeeder::class,
            DemoActivitySeeder::class,
        ]);

        // Ảnh thật từ Wikimedia Commons (database/data/media_external.json) làm ảnh bìa, SVG minh họa giữ làm ảnh phụ.
        Artisan::call('app:import-external-media');

        // Đồng bộ số lần sử dụng thẻ từ bảng taggables.
        DB::table('tags')->update([
            'usage_count' => DB::raw('(SELECT COUNT(*) FROM taggables WHERE taggables.tag_id = tags.id)'),
        ]);
    }
}
