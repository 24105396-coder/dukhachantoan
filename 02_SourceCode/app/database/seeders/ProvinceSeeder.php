<?php

namespace Database\Seeders;

use App\Models\Province;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        foreach ($this->readJson('provinces.json') as $row) {
            if (empty($row['code'])) {
                continue;
            }
            Province::updateOrCreate(['code' => $row['code']], [
                'name' => $row['name'],
                'name_en' => $row['name_en'] ?? $row['name'],
                'region' => $row['region'] ?? 'bac',
                'lat' => $row['lat'] ?? 0,
                'lng' => $row['lng'] ?? 0,
            ]);
        }
    }
}
