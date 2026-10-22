<?php

namespace Database\Seeders;

use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\Period;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

class ArtifactSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $periods = Period::pluck('id', 'code');
        $sites = HeritageSite::pluck('id', 'slug');

        foreach ($this->readJson('artifacts.json') as $row) {
            $siteId = $sites[$row['site'] ?? ''] ?? null;
            if (empty($row['slug']) || ! $siteId) {
                continue;
            }
            $type = $row['type'] ?? $row['artifact_type'] ?? 'khac';
            $artifact = Artifact::withTrashed()->updateOrCreate(['slug' => $row['slug']], [
                'site_id' => $siteId,
                'inventory_code' => $row['inventory_code'] ?? null,
                'artifact_type' => in_array($type, Artifact::TYPES, true) ? $type : 'khac',
                'material' => $row['material'] ?? null,
                'period_id' => $periods[$row['period'] ?? ''] ?? null,
                'year_from' => $row['year_from'] ?? null,
                'year_to' => $row['year_to'] ?? null,
                'height_cm' => $row['height_cm'] ?? null,
                'width_cm' => $row['width_cm'] ?? null,
                'depth_cm' => $row['depth_cm'] ?? null,
                'weight_kg' => $row['weight_kg'] ?? null,
                'is_national_treasure' => (bool) ($row['is_national_treasure'] ?? false),
                'model3d_url' => $row['model3d_url'] ?? null,
                'status' => $row['status'] ?? 'published',
            ]);
            $this->syncInterpretations($artifact, $row['interp'] ?? []);
            $this->syncTags($artifact, $row['tags'] ?? []);
        }
    }
}
