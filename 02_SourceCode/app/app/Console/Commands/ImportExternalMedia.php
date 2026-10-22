<?php

namespace App\Console\Commands;

use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\MediaAsset;
use App\Models\TourRoute;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Nhập ảnh thật từ Wikimedia Commons (database/data/media_external.json) làm ảnh bìa, kèm tác giả và giấy phép.
 * Idempotent: chạy lại chỉ cập nhật bản ghi cùng URL. Ảnh SVG minh họa cũ được giữ làm ảnh phụ.
 */
class ImportExternalMedia extends Command
{
    protected $signature = 'app:import-external-media {--file=database/data/media_external.json}';

    protected $description = 'Import Wikimedia Commons images as cover media with provenance';

    private const MODELS = ['site' => HeritageSite::class, 'artifact' => Artifact::class, 'figure' => HistoricalFigure::class];

    public function handle(): int
    {
        $path = base_path($this->option('file'));
        if (! is_file($path)) {
            $this->warn("Không có tệp {$path}, bỏ qua.");

            return self::SUCCESS;
        }
        $data = json_decode((string) file_get_contents($path), true);
        $items = $data['items'] ?? [];
        $fetched = $data['fetched_at'] ?? now()->toDateString();
        $done = 0;

        foreach ($items as $item) {
            $class = self::MODELS[$item['type'] ?? ''] ?? null;
            $subject = $class ? $class::query()->where('slug', $item['slug'])->first() : null;
            if (! $subject || empty($item['url'])) {
                continue;
            }
            DB::transaction(fn () => $this->attach($subject, $item, $fetched));
            $done++;
        }
        $this->info("Đã gắn {$done}/".count($items).' ảnh Wikimedia Commons làm ảnh bìa.');
        $this->info('Đã gắn '.$this->coverRoutes().' ảnh bìa tuyến tham quan từ điểm dừng đầu tiên có ảnh thật.');

        return self::SUCCESS;
    }

    /** Tuyến tham quan không có mục Wikimedia riêng: dùng lại ảnh bìa thật của điểm dừng đầu tiên có ảnh (giữ nguyên nguồn, giấy phép). */
    private function coverRoutes(): int
    {
        $done = 0;
        TourRoute::query()->with(['stops' => fn ($q) => $q->orderBy('stop_order'), 'stops.site'])->chunkById(50, function ($routes) use (&$done) {
            foreach ($routes as $route) {
                $source = null;
                foreach ($route->stops as $stop) {
                    $site = $stop->site;
                    $cover = $site?->cover_media_id ? MediaAsset::find($site->cover_media_id) : null;
                    if ($cover && $cover->external_url) {
                        $source = $cover;
                        break;
                    }
                }
                if (! $source) {
                    continue;
                }
                DB::transaction(function () use ($route, $source) {
                    $media = MediaAsset::updateOrCreate(
                        ['subject_type' => 'route', 'subject_id' => $route->id, 'media_type' => 'image', 'external_url' => $source->external_url],
                        $source->only(['mime', 'width', 'height', 'source_name', 'source_url', 'author', 'license', 'rights_holder', 'captured_at'])
                            + ['path' => null, 'sort_order' => 0, 'is_cover' => true,
                                'provenance_note' => trim(($source->provenance_note ?? '').' Dùng lại làm ảnh bìa tuyến tham quan (điểm dừng đầu tiên có ảnh thật).')]
                    );
                    foreach (['vi', 'en'] as $locale) {
                        $caption = $source->caption($locale)?->caption;
                        if ($caption) {
                            $media->captions()->updateOrCreate(['locale' => $locale], ['caption' => $caption]);
                        }
                    }
                    MediaAsset::query()->where('subject_type', 'route')->where('subject_id', $route->id)->whereKeyNot($media->id)->update(['is_cover' => false]);
                    $route->forceFill(['cover_media_id' => $media->id])->saveQuietly();
                });
                $done++;
            }
        });

        return $done;
    }

    private function attach(Model $subject, array $item, string $fetched): void
    {
        $type = $subject->getMorphClass();
        $media = MediaAsset::updateOrCreate(
            ['subject_type' => $type, 'subject_id' => $subject->getKey(), 'media_type' => 'image', 'external_url' => $item['url']],
            [
                'path' => null,
                'mime' => $item['mime'] ?? 'image/jpeg',
                'width' => $item['width'] ?? null,
                'height' => $item['height'] ?? null,
                'sort_order' => 0,
                'is_cover' => true,
                'source_name' => 'Wikimedia Commons',
                'source_url' => $item['source_url'] ?? null,
                'author' => mb_substr($item['author'] ?? 'Wikimedia Commons', 0, 150),
                'license' => in_array($item['license'] ?? '', MediaAsset::LICENSES, true) ? $item['license'] : 'cc_by_sa',
                'rights_holder' => mb_substr($item['author'] ?? 'Wikimedia Commons', 0, 190),
                'captured_at' => $this->date($item['captured_at'] ?? null),
                'provenance_note' => trim(sprintf('Tệp %s trên Wikimedia Commons, giấy phép %s%s. Truy cập %s qua bài Wikipedia tiếng Việt "%s".',
                    $item['file'] ?? '', $item['license_name'] ?? '', ! empty($item['license_url']) ? ' ('.$item['license_url'].')' : '', $fetched, $item['wikipedia'] ?? $item['title'])),
            ]
        );
        foreach (['vi' => $item['title'], 'en' => $item['title']] as $locale => $caption) {
            $media->captions()->updateOrCreate(['locale' => $locale], ['caption' => mb_substr($caption, 0, 500)]);
        }
        MediaAsset::query()->where('subject_type', $type)->where('subject_id', $subject->getKey())
            ->whereKeyNot($media->id)->update(['is_cover' => false]);
        $column = $type === 'figure' ? 'portrait_media_id' : 'cover_media_id';
        $subject->forceFill([$column => $media->id])->saveQuietly();
    }

    private function date(?string $value): ?string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false && $value <= now()->toDateString() ? $value : null;
    }
}
