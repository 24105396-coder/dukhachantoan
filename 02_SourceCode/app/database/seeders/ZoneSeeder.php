<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\Zone;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Vùng rủi ro từ database/data/zones.json (idempotent theo code; parent_code gắn ở lượt hai). */
class ZoneSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $rows = $this->readJson('zones.json');
        $provinces = Province::pluck('id', 'code');

        foreach ($rows as $row) {
            if (empty($row['code']) || empty($row['centroid'])) {
                continue;
            }
            Zone::updateOrCreate(['code' => $row['code']], [
                'zone_type' => $row['zone_type'] ?? 'custom',
                'province_id' => $provinces[$row['province_code'] ?? ''] ?? null,
                'name' => $this->pick($row['name'], 'vi') ?? $row['code'],
                'name_en' => $this->pick($row['name'], 'en'),
                'geometry' => $row['geometry'] ?? null,
                'centroid_lat' => $row['centroid']['lat'],
                'centroid_lng' => $row['centroid']['lng'],
                'radius_km' => $row['radius_km'] ?? null,
                'population_note' => $row['population_note'] ?? null,
                'source_name' => $row['source_name'] ?? null,
                'source_url' => $row['source_url'] ?? null,
                'is_active' => $row['is_active'] ?? true,
            ]);
        }

        $ids = Zone::pluck('id', 'code');
        foreach ($rows as $row) {
            if (! empty($row['code']) && array_key_exists('parent_code', $row)) {
                Zone::where('code', $row['code'])->update(['parent_id' => $ids[$row['parent_code'] ?? ''] ?? null]);
            }
        }
    }
}
