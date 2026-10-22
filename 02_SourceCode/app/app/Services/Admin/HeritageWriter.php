<?php

namespace App\Services\Admin;

use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\Interpretation;
use App\Models\TimelineEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PageCache;
use App\Services\PeriodResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ghi dữ liệu form quản trị cho di sản/hiện vật/nhân vật: cột chính, diễn giải
 * upsert theo (locale, depth), đồng bộ thẻ, nhân vật liên quan, sự kiện.
 */
class HeritageWriter
{
    public const SITE_FIELDS = ['slug', 'site_type', 'period_id', 'province_id', 'address', 'lat', 'lng', 'recognition_level',
        'year_recognized', 'year_built', 'visiting_hours', 'ticket_price_vnd', 'website', 'status'];
    public const ARTIFACT_FIELDS = ['slug', 'site_id', 'inventory_code', 'artifact_type', 'material', 'period_id', 'year_from', 'year_to',
        'height_cm', 'width_cm', 'depth_cm', 'weight_kg', 'is_national_treasure', 'model3d_url', 'status'];
    public const FIGURE_FIELDS = ['slug', 'figure_type', 'birth_year', 'death_year', 'period_id', 'province_id', 'status'];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function saveSite(array $data, User $user, ?HeritageSite $site = null): HeritageSite
    {
        return $this->save($site ?? new HeritageSite(), Arr::only($data, self::SITE_FIELDS), $data, $user, function (HeritageSite $s) use ($data) {
            $s->figures()->sync(collect($data['figures'] ?? [])->mapWithKeys(fn ($f) => [
                (int) $f['figure_id'] => ['relation_type' => $f['relation_type']],
            ])->all());
            $this->syncEvents($s, 'site_id', $data['events'] ?? []);
        });
    }

    public function saveArtifact(array $data, User $user, ?Artifact $artifact = null): Artifact
    {
        $data['is_national_treasure'] = (bool) ($data['is_national_treasure'] ?? false);

        return $this->save($artifact ?? new Artifact(), Arr::only($data, self::ARTIFACT_FIELDS), $data, $user);
    }

    public function saveFigure(array $data, User $user, ?HistoricalFigure $figure = null): HistoricalFigure
    {
        return $this->save($figure ?? new HistoricalFigure(), Arr::only($data, self::FIGURE_FIELDS), $data, $user, function (HistoricalFigure $f) use ($data) {
            $this->syncEvents($f, 'figure_id', $data['events'] ?? []);
        });
    }

    public function delete(Model $model, User $user): void
    {
        $model->delete();
        $this->audit->log('admin.'.$model->getMorphClass().'.delete', $model, ['slug' => $model->slug], $user);
        Cache::forget('stats:overview');
        // Sau commit (thẻ/pivot không phát sự kiện model) → làm mới bộ đệm trang công khai
        app(PageCache::class)->flush();
    }

    private function save(Model $model, array $fields, array $data, User $user, ?callable $extra = null): Model
    {
        $isNew = ! $model->exists;

        DB::transaction(function () use ($model, $fields, $data, $user, $isNew, $extra) {
            $model->fill($fields);
            if ($isNew) {
                $model->created_by = $user->id;
            }
            $model->save();

            $this->syncInterpretations($model, $data['interpretations'] ?? [], $user);
            $model->tags()->sync($data['tag_ids'] ?? []);
            if ($extra) {
                $extra($model);
            }
        });

        $this->audit->log('admin.'.$model->getMorphClass().'.'.($isNew ? 'create' : 'update'), $model, ['slug' => $model->slug], $user);
        Cache::forget('stats:overview');
        // Sau commit (thẻ/pivot không phát sự kiện model) → làm mới bộ đệm trang công khai
        app(PageCache::class)->flush();

        return $model;
    }

    /** Upsert theo (locale, depth); bản bị bỏ khỏi form thì xóa; đổi nội dung thì tăng version. */
    private function syncInterpretations(Model $model, array $items, User $user): void
    {
        $keep = [];
        foreach ($items as $item) {
            $sources = is_array($item['sources'] ?? null) ? implode("\n", array_filter($item['sources'])) : ($item['sources'] ?? null);
            $interp = Interpretation::query()->firstOrNew([
                'subject_type' => $model->getMorphClass(),
                'subject_id' => $model->getKey(),
                'locale' => $item['locale'],
                'depth_level' => (int) $item['depth_level'],
            ]);
            $interp->fill([
                'title' => $item['title'],
                'summary' => $item['summary'] ?? null,
                'body' => $item['body'],
                'sources' => $sources,
                'reviewed_by' => $user->id,
            ]);
            if (! $interp->exists) {
                $interp->author_id = $user->id;
            } elseif ($interp->isDirty(['title', 'summary', 'body', 'sources'])) {
                $interp->version = (int) $interp->version + 1;
            }
            $interp->save();
            $keep[] = $interp->id;
        }

        Interpretation::query()->where('subject_type', $model->getMorphClass())->where('subject_id', $model->getKey())
            ->whereNotIn('id', $keep)->delete();
    }

    private function syncEvents(Model $model, string $foreignKey, array $events): void
    {
        $keep = [];
        $periods = app(PeriodResolver::class);
        foreach ($events as $e) {
            $event = ! empty($e['id'])
                ? TimelineEvent::query()->where($foreignKey, $model->getKey())->find($e['id']) ?? new TimelineEvent()
                : new TimelineEvent();
            $event->fill([
                $foreignKey => $model->getKey(),
                // Thời kỳ theo năm sự kiện, không chép nguyên thời kỳ của chủ thể
                'period_id' => $periods->forYear(isset($e['event_year']) ? (int) $e['event_year'] : null, $model->period_id),
                'event_type' => $e['event_type'],
                'event_year' => $e['event_year'],
                'event_month' => $e['event_month'] ?? null,
                'event_day' => $e['event_day'] ?? null,
                'end_year' => $e['end_year'] ?? null,
                'importance' => $e['importance'] ?? 2,
            ])->save();

            foreach (['vi', 'en'] as $locale) {
                $title = $e['title_'.$locale] ?? null;
                if ($title) {
                    $event->translations()->updateOrCreate(['locale' => $locale], [
                        'title' => $title, 'description' => $e['description_'.$locale] ?? null,
                    ]);
                }
            }
            $keep[] = $event->id;
        }
        TimelineEvent::query()->where($foreignKey, $model->getKey())->whereNotIn('id', $keep)->delete();
    }
}
