<?php

namespace Database\Seeders;

use App\Models\HistoricalFigure;
use App\Models\Period;
use App\Models\Province;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

class FigureSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $periods = Period::pluck('id', 'code');
        $provinces = Province::pluck('id', 'code');

        foreach ($this->readJson('figures.json') as $row) {
            if (empty($row['slug'])) {
                continue;
            }
            $type = $row['type'] ?? $row['figure_type'] ?? 'khac';
            $periodId = $periods[$row['period'] ?? ''] ?? null;
            $figure = HistoricalFigure::withTrashed()->updateOrCreate(['slug' => $row['slug']], [
                'figure_type' => in_array($type, HistoricalFigure::TYPES, true) ? $type : 'khac',
                'birth_year' => $row['birth_year'] ?? null,
                'death_year' => $row['death_year'] ?? null,
                'period_id' => $periodId,
                'province_id' => $provinces[$row['province'] ?? ''] ?? null,
                'status' => $row['status'] ?? 'published',
            ]);
            $this->syncInterpretations($figure, $row['interp'] ?? []);
            $this->syncTags($figure, $row['tags'] ?? []);
            $this->syncEvents('figure_id', $figure, $row['events'] ?? [], $periodId);
        }
    }
}
