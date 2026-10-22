<?php

namespace Database\Seeders;

use App\Models\HazardType;
use App\Models\ResponseGuide;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/** Hướng dẫn ứng phó từ database/data/response_guides.json (idempotent theo slug). */
class GuideSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $hazards = HazardType::pluck('id', 'code');

        foreach ($this->readJson('response_guides.json') as $i => $row) {
            $hazardId = $hazards[$row['hazard_code'] ?? ''] ?? null;
            if (empty($row['slug']) || ! $hazardId) {
                $this->warnSeed('bỏ qua hướng dẫn thiếu slug hoặc hazard_code không tồn tại: '.($row['slug'] ?? '?'));

                continue;
            }
            $guide = ResponseGuide::updateOrCreate(['slug' => $row['slug']], [
                'hazard_type_id' => $hazardId,
                'min_severity' => (int) ($row['min_severity'] ?? 1),
                'audience' => $row['audience'] ?? 'all',
                'sort_order' => (int) ($row['sort_order'] ?? $i),
                'status' => $row['status'] ?? 'published',
            ]);
            $translations = $this->translationsFrom($row, [
                'title' => 'title', 'summary' => 'summary',
                'before_steps' => 'before_steps', 'during_steps' => 'during_steps', 'after_steps' => 'after_steps',
            ]);
            foreach ($translations as $locale => $fields) {
                $translations[$locale]['sources'] = $this->pick($row['sources'] ?? [], $locale) ?? [];
                $machine = $row['is_machine_translated'] ?? false;
                $translations[$locale]['is_machine_translated'] = (bool) (is_array($machine) ? ($machine[$locale] ?? false) : $machine);
            }
            $guide->syncTranslations(array_filter($translations, fn ($t) => ! empty($t['title'])));
        }
    }
}
