<?php

namespace Database\Seeders;

use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\MediaAsset;
use App\Models\MediaCaption;
use App\Models\Period;
use App\Models\Province;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    use ReadsSeedJson;

    private const RELATIONS = ['sang_lap', 'duoc_tho', 'gan_lien', 'trung_tu', 'sinh_ra', 'an_tang'];

    public function run(): void
    {
        $periods = Period::pluck('id', 'code');
        $provinces = Province::pluck('id', 'code');
        $figures = HistoricalFigure::pluck('id', 'slug');

        foreach ($this->globJson('sites-*.json') as $row) {
            $provinceId = $provinces[$row['province'] ?? ''] ?? null;
            if (empty($row['slug']) || ! $provinceId) {
                continue;
            }
            $periodId = $periods[$row['period'] ?? ''] ?? null;
            $site = HeritageSite::withTrashed()->updateOrCreate(['slug' => $row['slug']], [
                'site_type' => in_array($row['site_type'] ?? null, HeritageSite::TYPES, true) ? $row['site_type'] : 'di_tich',
                'period_id' => $periodId,
                'province_id' => $provinceId,
                'address' => $row['address'] ?? null,
                'lat' => $row['lat'] ?? 0,
                'lng' => $row['lng'] ?? 0,
                'recognition_level' => in_array($row['recognition_level'] ?? null, HeritageSite::LEVELS, true) ? $row['recognition_level'] : 'chua_xep_hang',
                'year_recognized' => $row['year_recognized'] ?? null,
                'year_built' => $row['year_built'] ?? null,
                'visiting_hours' => isset($row['visiting_hours']) ? mb_substr($row['visiting_hours'], 0, 120) : null,
                'ticket_price_vnd' => $row['ticket_price_vnd'] ?? null,
                'website' => $row['website'] ?? null,
                'status' => $row['status'] ?? 'published',
            ]);
            $this->syncInterpretations($site, $row['interp'] ?? []);
            $this->syncTags($site, $row['tags'] ?? []);
            $this->syncEvents('site_id', $site, $row['events'] ?? [], $periodId);
            $this->syncFigures($site, $row['figures'] ?? [], $figures->all());
            $this->syncDeclaredMedia($site, $row['media'] ?? []);
        }
    }

    private function syncFigures(HeritageSite $site, array $items, array $figureIds): void
    {
        $sync = [];
        foreach ($items as $item) {
            $id = $figureIds[$item['slug'] ?? ''] ?? null;
            if ($id) {
                $relation = in_array($item['relation'] ?? null, self::RELATIONS, true) ? $item['relation'] : 'gan_lien';
                $sync[$id] = ['relation_type' => $relation];
            }
        }
        $site->figures()->syncWithoutDetaching($sync);
    }

    /** Ảnh thật khai báo trong JSON (liên kết ngoài, có giấy phép rõ); sort_order từ 10 để không đè ảnh minh họa. */
    private function syncDeclaredMedia(HeritageSite $site, array $items): void
    {
        foreach (array_values($items) as $i => $m) {
            if (empty($m['external_url'])) {
                continue;
            }
            $media = MediaAsset::updateOrCreate(
                ['subject_type' => $site->getMorphClass(), 'subject_id' => $site->id, 'external_url' => $m['external_url']],
                [
                    'media_type' => $m['type'] ?? 'image',
                    'sort_order' => 10 + $i,
                    'source_name' => $m['source_name'] ?? null,
                    'source_url' => $m['source_url'] ?? null,
                    'author' => $m['author'] ?? null,
                    'license' => in_array($m['license'] ?? null, MediaAsset::LICENSES, true) ? $m['license'] : 'all_rights_reserved',
                    'rights_holder' => $m['rights_holder'] ?? null,
                ],
            );
            foreach (['vi', 'en'] as $locale) {
                if (! empty($m['caption_'.$locale])) {
                    MediaCaption::updateOrCreate(['media_id' => $media->id, 'locale' => $locale], ['caption' => mb_substr($m['caption_'.$locale], 0, 500)]);
                }
            }
        }
    }
}
