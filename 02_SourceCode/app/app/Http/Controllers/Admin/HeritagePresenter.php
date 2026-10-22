<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\MediaItemResource;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;

/** Chuẩn hóa dữ liệu bảng/form quản trị cho di sản, hiện vật, nhân vật. */
final class HeritagePresenter
{
    public static function row(Model $m): array
    {
        $interps = $m->interpretations;
        $title = fn (string $locale) => $interps->where('locale', $locale)->sortBy('depth_level')->first()?->title;

        return [
            'id' => $m->id,
            'slug' => $m->slug,
            'title_vi' => $title('vi') ?? $m->slug,
            'title_en' => $title('en'),
            'status' => $m->status,
            'depths_vi' => $interps->where('locale', 'vi')->pluck('depth_level')->sort()->values()->all(),
            'depths_en' => $interps->where('locale', 'en')->pluck('depth_level')->sort()->values()->all(),
            'media_count' => (int) ($m->media_count ?? 0),
            'view_count' => (int) $m->view_count,
            'updated_at' => $m->updated_at?->toIso8601String(),
        ];
    }

    public static function form(Model $m, array $columns): array
    {
        $m->loadMissing(['interpretations', 'media.captions', 'tags']);
        $data = ['id' => $m->id];
        foreach ($columns as $column) {
            $data[$column] = $m->{$column};
        }

        $titleVi = $m->interpretations->where('locale', 'vi')->sortBy('depth_level')->first()?->title;

        return $data + [
            'title_vi' => $titleVi ?? $m->slug,
            'interpretations' => $m->interpretations->sortBy([fn ($a, $b) => [$a->locale !== 'vi', $a->depth_level] <=> [$b->locale !== 'vi', $b->depth_level]])->map(fn ($i) => [
                'id' => $i->id,
                'locale' => $i->locale,
                'depth_level' => $i->depth_level,
                'title' => $i->title,
                'summary' => $i->summary,
                'body' => $i->body,
                'sources' => self::sourceList($i->sources),
                'version' => $i->version,
            ])->values()->all(),
            'media' => MediaItemResource::list($m->media),
            'tag_ids' => $m->tags->pluck('id')->all(),
        ];
    }

    /** Cột sources lưu nhiều dòng; form nhận mảng chuỗi. */
    public static function sourceList(mixed $sources): array
    {
        if (is_array($sources)) {
            return array_values(array_filter(array_map('trim', $sources), 'strlen'));
        }

        return array_values(array_filter(array_map('trim', explode("\n", (string) $sources)), 'strlen'));
    }

    public static function events($events): array
    {
        return $events->map(function ($e) {
            $vi = $e->translations->firstWhere('locale', 'vi');
            $en = $e->translations->firstWhere('locale', 'en');

            return [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'event_year' => $e->event_year,
                'event_month' => $e->event_month,
                'event_day' => $e->event_day,
                'end_year' => $e->end_year,
                'importance' => $e->importance,
                'title_vi' => $vi?->title,
                'title_en' => $en?->title,
                'description_vi' => $vi?->description,
                'description_en' => $en?->description,
            ];
        })->values()->all();
    }

    public static function enumList(string $group, array $codes): array
    {
        return array_map(fn ($c) => ['code' => $c, 'name' => __("enums.$group.$c")], $codes);
    }

    public static function commonEnums(): array
    {
        return [
            'statuses' => self::enumList('statuses', ['draft', 'published', 'archived']),
            'event_types' => self::enumList('event_types', ['xay_dung', 'trung_tu', 'cong_nhan', 'su_kien', 'sinh', 'mat', 'phat_hien', 'khac']),
            'licenses' => self::enumList('licenses', MediaAsset::LICENSES),
            'depths' => [1, 2, 3],
            'locales' => ['vi', 'en'],
        ];
    }
}
