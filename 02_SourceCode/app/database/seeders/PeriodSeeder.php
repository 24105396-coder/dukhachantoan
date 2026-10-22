<?php

namespace Database\Seeders;

use App\Models\Period;
use App\Models\PeriodTranslation;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

class PeriodSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        foreach ($this->readJson('periods.json') as $i => $row) {
            if (empty($row['code'])) {
                continue;
            }
            $period = Period::updateOrCreate(['code' => $row['code']], [
                'start_year' => $row['start_year'] ?? 0,
                'end_year' => $row['end_year'] ?? null,
                'sort_order' => $row['sort_order'] ?? $i + 1,
            ]);
            foreach (['vi', 'en'] as $locale) {
                if (! empty($row[$locale]['name'])) {
                    PeriodTranslation::updateOrCreate(
                        ['period_id' => $period->id, 'locale' => $locale],
                        ['name' => $row[$locale]['name'], 'description' => $row[$locale]['description'] ?? null],
                    );
                }
            }
        }
    }
}
