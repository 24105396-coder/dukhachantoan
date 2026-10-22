<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Models\TourRoute;
use Database\Seeders\Support\SvgIllustration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Vẽ lại toàn bộ ảnh SVG minh họa đang có trong storage theo mẫu SvgIllustration hiện hành
 * (dùng khi đổi phông, màu); không đổi bản ghi media_assets. Idempotent, chạy ở entrypoint.
 */
class RenderIllustrations extends Command
{
    protected $signature = 'app:render-illustrations';

    protected $description = 'Re-render generated SVG illustration files with the current template';

    public function handle(): int
    {
        $count = 0;
        MediaAsset::query()->where('media_type', 'image')->where('path', 'like', 'media/%.svg')
            ->with('subject')->chunkById(100, function ($items) use (&$count) {
                foreach ($items as $media) {
                    $subject = $media->subject;
                    if (! $subject || ! preg_match('/-(\d+)\.svg$/', (string) $media->path, $m)) {
                        continue;
                    }
                    $title = $subject instanceof TourRoute
                        ? ($subject->translate('vi')?->name ?? $subject->slug)
                        : ($subject->interpretations()->where('locale', 'vi')->where('depth_level', 1)->value('title') ?? $subject->slug);
                    Storage::disk('public')->put($media->path, SvgIllustration::make($media->subject_type, $title, (int) $m[1]));
                    $count++;
                }
            });
        $this->info("Đã vẽ lại {$count} ảnh SVG minh họa.");

        return self::SUCCESS;
    }
}
