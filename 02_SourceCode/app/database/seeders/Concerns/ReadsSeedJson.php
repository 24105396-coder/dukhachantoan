<?php

namespace Database\Seeders\Concerns;

use App\Models\Interpretation;
use Illuminate\Database\Eloquent\Model;

/**
 * Đọc dữ liệu hạt giống JSON trong database/data; file thiếu hoặc lỗi cú pháp trả mảng rỗng
 * để seeder chạy lại được khi dữ liệu chưa đủ.
 */
trait ReadsSeedJson
{
    protected function dataPath(string $file = ''): string
    {
        return database_path('data'.($file !== '' ? '/'.$file : ''));
    }

    protected function readJson(string $file): array
    {
        $path = str_starts_with($file, '/') ? $file : $this->dataPath($file);
        if (! is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /** Gộp mọi file khớp mẫu (ví dụ sites-*.json) thành một mảng. */
    protected function globJson(string $pattern): array
    {
        $files = glob($this->dataPath($pattern)) ?: [];
        sort($files);
        $items = [];
        foreach ($files as $file) {
            array_push($items, ...$this->readJson($file));
        }

        return $items;
    }

    /** Ghi các lớp diễn giải từ khối "interp" {locale: {depth: {...}}}. */
    protected function syncInterpretations(Model $subject, array $interp): void
    {
        foreach ($interp as $locale => $depths) {
            foreach ((array) $depths as $depth => $row) {
                if (! is_array($row) || empty($row['title'])) {
                    continue;
                }
                $sources = $row['sources'] ?? null;
                Interpretation::updateOrCreate(
                    ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'locale' => $locale, 'depth_level' => (int) $depth],
                    [
                        'title' => mb_substr($row['title'], 0, 255),
                        'summary' => $row['summary'] ?? null,
                        'body' => $row['body'] ?? '',
                        'sources' => is_array($sources) ? implode("\n", $sources) : $sources,
                        'is_machine_translated' => (bool) ($row['is_machine_translated'] ?? false),
                    ],
                );
            }
        }
    }

    /** Gắn thẻ theo danh sách slug, bỏ qua slug không tồn tại. */
    protected function syncTags(Model $subject, array $slugs): void
    {
        $ids = \App\Models\Tag::whereIn('slug', $slugs)->pluck('id')->all();
        $subject->tags()->sync($ids);
    }

    /** Tạo lại sự kiện dòng thời gian của một di sản/nhân vật; $periodId của chủ thể chỉ là gợi ý ưu tiên. */
    protected function syncEvents(string $column, Model $subject, array $events, ?int $periodId): void
    {
        \App\Models\TimelineEvent::where($column, $subject->getKey())->delete();
        $periods = app(\App\Services\PeriodResolver::class);
        foreach ($events as $ev) {
            if (empty($ev['year']) && ($ev['year'] ?? null) !== 0) {
                continue;
            }
            $event = \App\Models\TimelineEvent::create([
                $column => $subject->getKey(),
                'period_id' => $periods->forYear((int) $ev['year'], $periodId),
                'event_type' => $ev['type'] ?? 'khac',
                'event_year' => (int) $ev['year'],
                'event_month' => $ev['month'] ?? null,
                'event_day' => $ev['day'] ?? null,
                'end_year' => $ev['end_year'] ?? null,
                'importance' => (int) ($ev['importance'] ?? 2),
            ]);
            foreach (['vi', 'en'] as $locale) {
                if (! empty($ev[$locale]['title'])) {
                    \App\Models\TimelineEventTranslation::create([
                        'event_id' => $event->id,
                        'locale' => $locale,
                        'title' => mb_substr($ev[$locale]['title'], 0, 255),
                        'description' => $ev[$locale]['description'] ?? null,
                    ]);
                }
            }
        }
    }
}
