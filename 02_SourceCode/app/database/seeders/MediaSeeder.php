<?php

namespace Database\Seeders;

use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\MediaAsset;
use App\Models\MediaCaption;
use App\Models\TourRoute;
use Database\Seeders\Support\SvgIllustration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Ảnh minh họa SVG (license simulated) và thuyết minh TTS (không có tệp, đọc transcript bằng Web Speech API).
 */
class MediaSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([HeritageSite::class => 'cover_media_id', Artifact::class => 'cover_media_id', HistoricalFigure::class => 'portrait_media_id'] as $class => $coverColumn) {
            $class::with('interpretations')->chunkById(50, function ($items) use ($coverColumn) {
                foreach ($items as $item) {
                    $this->seedFor($item, $coverColumn);
                }
            });
        }
        TourRoute::with('translations')->get()->each(function (TourRoute $route) {
            $title = $route->translate('vi')?->name ?? $route->slug;
            $titleEn = $route->translate('en')?->name ?? $title;
            $cover = $this->image($route, 1, $title, $titleEn);
            $route->forceFill(['cover_media_id' => $cover->id])->saveQuietly();
        });
    }

    private function seedFor(Model $item, string $coverColumn): void
    {
        // Chọn đúng locale (không dùng fallback) để audio en không đọc văn bản tiếng Việt.
        $pick = fn (string $l) => $item->interpretations->first(fn ($i) => $i->locale === $l && (int) $i->depth_level === 1);
        $vi = $pick('vi');
        $en = $pick('en');
        $titleVi = $vi?->title ?? $item->slug;
        $titleEn = $en?->title ?? $titleVi;

        $cover = $this->image($item, 1, $titleVi, $titleEn);
        $this->image($item, 2, $titleVi, $titleEn);
        $item->forceFill([$coverColumn => $cover->id])->saveQuietly();

        foreach (['vi' => $vi, 'en' => $en] as $locale => $interp) {
            if ($interp) {
                $this->audio($item, $locale, $interp->title, trim(($interp->summary ?? '')."\n\n".$interp->body));
            }
        }
    }

    private function image(Model $item, int $n, string $titleVi, string $titleEn): MediaAsset
    {
        $type = $item->getMorphClass();
        $path = "media/{$type}/{$item->slug}-{$n}.svg";
        $svg = SvgIllustration::make($type, $titleVi, $n);
        Storage::disk('public')->put($path, $svg);

        $media = MediaAsset::updateOrCreate(
            ['subject_type' => $type, 'subject_id' => $item->getKey(), 'media_type' => 'image', 'sort_order' => $n],
            [
                'path' => $path,
                'external_url' => null,
                'mime' => 'image/svg+xml',
                'size_bytes' => strlen($svg),
                'width' => 1200,
                'height' => 800,
                'is_cover' => $n === 1,
                'source_name' => 'Ảnh minh họa do nhóm tạo',
                'author' => 'Nhóm DiSanSo',
                'license' => 'simulated',
                'rights_holder' => 'Nhóm DiSanSo',
                'provenance_note' => null,
            ],
        );
        $this->caption($media, 'vi', "Ảnh minh họa {$n}: {$titleVi}");
        $this->caption($media, 'en', "Illustration {$n}: {$titleEn}");

        return $media;
    }

    private function audio(Model $item, string $locale, string $title, string $transcript): void
    {
        $media = MediaAsset::updateOrCreate(
            ['subject_type' => $item->getMorphClass(), 'subject_id' => $item->getKey(), 'media_type' => 'audio', 'locale' => $locale],
            [
                'path' => null,
                'mime' => null,
                'sort_order' => $locale === 'vi' ? 1 : 2,
                'duration_sec' => (int) ceil(count(preg_split('/\\s+/u', $transcript) ?: []) / 2.5) ?: null,
                'source_name' => 'Thuyết minh tổng hợp giọng nói (TTS)',
                'author' => 'Nhóm DiSanSo',
                'license' => 'simulated',
                'rights_holder' => 'Nhóm DiSanSo',
                'provenance_note' => 'Văn bản thuyết minh từ lớp diễn giải phổ thông; trình duyệt đọc bằng Web Speech API.',
            ],
        );
        $label = $locale === 'vi' ? 'Thuyết minh: ' : 'Audio guide: ';
        $this->caption($media, $locale, $label.$title, $transcript);
    }

    private function caption(MediaAsset $media, string $locale, string $caption, ?string $transcript = null): void
    {
        MediaCaption::updateOrCreate(
            ['media_id' => $media->id, 'locale' => $locale],
            ['caption' => mb_substr($caption, 0, 500), 'transcript' => $transcript],
        );
    }
}
