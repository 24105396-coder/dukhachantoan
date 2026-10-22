<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\TagTranslation;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $rows = $this->readJson('tags.json');
        foreach ($rows as $row) {
            if (empty($row['slug'])) {
                continue;
            }
            $tag = Tag::updateOrCreate(['slug' => $row['slug']], [
                'tag_group' => in_array($row['group'] ?? null, Tag::GROUPS, true) ? $row['group'] : 'chu_de',
            ]);
            foreach (['vi', 'en'] as $locale) {
                if (! empty($row[$locale]['name'])) {
                    TagTranslation::updateOrCreate(
                        ['tag_id' => $tag->id, 'locale' => $locale],
                        ['name' => $row[$locale]['name'], 'description' => isset($row[$locale]['description']) ? mb_substr($row[$locale]['description'], 0, 500) : null],
                    );
                }
            }
        }
        // Gắn thẻ cha sau khi mọi thẻ đã tồn tại.
        foreach ($rows as $row) {
            if (! empty($row['parent'])) {
                $parentId = Tag::where('slug', $row['parent'])->value('id');
                Tag::where('slug', $row['slug'])->update(['parent_id' => $parentId]);
            }
        }
    }
}
