<?php

namespace Database\Seeders;

use App\Models\HazardType;
use App\Models\SimulationScenario;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Kịch bản mô phỏng từ database/data/scenarios.json (idempotent theo slug; giữ trạng thái chạy hiện có). */
class ScenarioSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $hazards = HazardType::pluck('id', 'code');

        foreach ($this->readJson('scenarios.json') as $row) {
            $hazardId = $hazards[$row['hazard_code'] ?? ''] ?? null;
            if (empty($row['slug']) || ! $hazardId) {
                $this->warnSeed('bỏ qua kịch bản thiếu slug hoặc hazard_code không tồn tại: '.($row['slug'] ?? '?'));

                continue;
            }
            $scenario = SimulationScenario::firstOrNew(['slug' => $row['slug']]);
            $scenario->fill([
                'name' => $this->pick($row['name'] ?? $row['slug'], 'vi') ?? $row['slug'],
                'description' => $this->pick($row['description'] ?? null, 'vi'),
                'hazard_type_id' => $hazardId,
                'severity' => (int) ($row['severity'] ?? 3),
                'zone_codes' => array_values($row['zone_codes'] ?? []),
                'duration_hours' => (int) ($row['duration_hours'] ?? 24),
                'payload' => [
                    'name' => $row['name'] ?? null,
                    'description' => $row['description'] ?? null,
                    'steps' => $row['steps'] ?? [],
                ],
            ]);
            $scenario->status ??= 'ready';
            $scenario->save();
        }
    }
}
