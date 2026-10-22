<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Province;
use App\Models\Zone;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Điểm đến từ database/data/destinations.json (idempotent theo slug). */
class DestinationSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $zones = Zone::pluck('id', 'code');
        $provinces = Province::pluck('id', 'code');

        foreach ($this->readJson('destinations.json') as $row) {
            $zoneId = $zones[$row['zone_code'] ?? ''] ?? null;
            if (empty($row['slug']) || ! $zoneId) {
                $this->warnSeed('bỏ qua điểm đến thiếu slug hoặc zone_code không tồn tại: '.($row['slug'] ?? '?'));

                continue;
            }
            $destination = Destination::updateOrCreate(['slug' => $row['slug']], [
                'zone_id' => $zoneId,
                'province_id' => $provinces[$row['province_code'] ?? ''] ?? null,
                'dest_type' => $row['dest_type'] ?? 'khac',
                'lat' => $row['lat'],
                'lng' => $row['lng'],
                'altitude_m' => $row['altitude_m'] ?? null,
                'is_popular' => (bool) ($row['is_popular'] ?? false),
            ]);
            $destination->syncTranslations(array_filter(
                $this->translationsFrom($row, ['name' => 'name', 'description' => 'description', 'safety_note' => 'safety_note']),
                fn ($t) => ! empty($t['name']),
            ));
        }
    }
}
