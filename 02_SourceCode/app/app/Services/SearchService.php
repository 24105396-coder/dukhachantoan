<?php

namespace App\Services;

use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\Interpretation;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tìm kiếm ngữ nghĩa qua dịch vụ Python (TF-IDF); khi dịch vụ tắt/lỗi thì dùng
 * FULLTEXT MySQL, cuối cùng là LIKE tham số hóa. Mỗi lượt tìm được ghi search_logs.
 */
class SearchService
{
    public const TYPES = ['site' => HeritageSite::class, 'artifact' => Artifact::class, 'figure' => HistoricalFigure::class];

    public function __construct(private readonly AnalyticsClient $analytics)
    {
    }

    /** @return array{engine: string, results: array, durationMs: int} */
    public function search(string $q, string $locale, ?User $user = null, int $limit = 20, ?array $types = null): array
    {
        $q = Str::limit(trim($q), 200, '');
        $started = microtime(true);
        $types = $types ? array_values(array_intersect($types, array_keys(self::TYPES))) : array_keys(self::TYPES);

        if ($q === '') {
            return ['engine' => 'fallback', 'results' => [], 'durationMs' => 0];
        }

        $engine = 'semantic';
        $raw = $this->semantic($q, $locale, $limit, $types);
        $results = $raw ? $this->hydrate($raw, $q) : [];
        // Python tắt/lỗi (null) hoặc chỉ mục rỗng/không khớp bản công bố ([]) → FULLTEXT MySQL
        if ($results === []) {
            if ($raw !== null) {
                // Không ghi nguyên chuỗi tìm kiếm (có thể chứa dữ liệu cá nhân)
                Log::info('Semantic search returned no results, using fallback', ['q_length' => mb_strlen($q), 'locale' => $locale, 'engine' => 'fallback']);
            }
            $engine = 'fallback';
            $results = $this->hydrate($this->fallback($q, $locale, $limit, $types), $q);
        }
        $duration = (int) round((microtime(true) - $started) * 1000);

        $this->log($q, $locale, $user, count($results), $engine, $duration);

        return ['engine' => $engine, 'results' => $results, 'durationMs' => $duration];
    }

    /**
     * Từ khóa phổ biến 30 ngày gần nhất: chỉ truy vấn có kết quả của người dùng đã đăng nhập,
     * xếp theo số người khác nhau đã tìm (khách vãng lai không đầu độc được danh sách).
     */
    public function popularQueries(string $locale, int $limit = 8): array
    {
        return SearchLog::query()
            ->where('locale', $locale)->where('result_count', '>', 0)->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subDays(30))
            ->select('query', DB::raw('COUNT(DISTINCT user_id) as users'), DB::raw('COUNT(*) as total'))
            ->groupBy('query')->orderByDesc('users')->orderByDesc('total')->limit($limit)
            ->pluck('query')->all();
    }

    private function semantic(string $q, string $locale, int $limit, array $types): ?array
    {
        $response = $this->analytics->search($q, $locale, $limit, $types);
        if ($response === null) {
            return null;
        }

        if (! is_array($response['results'] ?? null)) {
            return null;
        }

        return collect($response['results'])
            ->filter(fn ($r) => isset($r['subject_type'], $r['subject_id']) && in_array($r['subject_type'], $types, true))
            ->map(fn ($r) => [
                'type' => $r['subject_type'],
                'id' => (int) $r['subject_id'],
                'score' => round((float) ($r['score'] ?? 0), 4),
                'matched_terms' => array_values(array_map('strval', (array) ($r['matched_terms'] ?? []))),
            ])->values()->all();
    }

    private function fallback(string $q, string $locale, int $limit, array $types): array
    {
        $rows = $this->fullText($q, $locale, $types);
        if ($rows->isEmpty()) {
            $rows = $this->like($q, $locale, $types);
        }

        return $rows->groupBy(fn ($r) => $r->subject_type.':'.$r->subject_id)
            ->map(fn (Collection $group) => [
                'type' => $group->first()->subject_type,
                'id' => (int) $group->first()->subject_id,
                'score' => round((float) $group->max('score'), 4),
                'matched_terms' => [],
            ])
            ->sortByDesc('score')->take($limit)->values()->all();
    }

    private function fullText(string $q, string $locale, array $types): Collection
    {
        try {
            return Interpretation::query()
                ->select(['subject_type', 'subject_id'])
                ->selectRaw('MATCH(title, summary, body) AGAINST (? IN NATURAL LANGUAGE MODE) AS score', [$q])
                ->whereIn('subject_type', $types)
                ->where('locale', $locale)
                ->whereFullText(['title', 'summary', 'body'], $q)
                ->orderByDesc('score')->limit(100)->get();
        } catch (QueryException $e) {
            report($e);

            return collect();
        }
    }

    private function like(string $q, string $locale, array $types): Collection
    {
        $pattern = like_contains($q);

        return Interpretation::query()
            ->select(['subject_type', 'subject_id', 'title', 'summary'])
            ->whereIn('subject_type', $types)
            ->where('locale', $locale)
            ->where(fn ($w) => $w->where('title', 'like', $pattern)
                ->orWhere('summary', 'like', $pattern)
                ->orWhere('body', 'like', $pattern))
            ->limit(100)->get()
            ->each(function ($row) use ($q) {
                $row->score = Str::contains(Str::lower($row->title), Str::lower($q)) ? 3
                    : (Str::contains(Str::lower((string) $row->summary), Str::lower($q)) ? 2 : 1);
            });
    }

    /** Nạp thực thể đã công bố và chuẩn hóa thành SearchHit. */
    private function hydrate(array $raw, string $q): array
    {
        $models = [];
        foreach (collect($raw)->groupBy('type') as $type => $items) {
            $class = self::TYPES[$type] ?? null;
            if (! $class) {
                continue;
            }
            $models[$type] = $class::query()->published()->whereIn('id', $items->pluck('id'))
                ->with(['interpretations' => fn ($i) => $i->where('depth_level', 1), 'media.captions'])
                ->get()->keyBy('id');
        }

        $terms = $this->terms($q);

        return collect($raw)->map(function ($hit) use ($models, $terms) {
            $model = $models[$hit['type']][$hit['id']] ?? null;
            if (! $model) {
                return null;
            }

            return $this->toHit($hit, $model, $terms);
        })->filter()->values()->all();
    }

    private function toHit(array $hit, $model, array $terms): array
    {
        $routeName = ['site' => 'sites.show', 'artifact' => 'artifacts.show', 'figure' => 'figures.show'][$hit['type']];
        $param = ['site' => 'site', 'artifact' => 'artifact', 'figure' => 'figure'][$hit['type']];
        $haystack = Str::lower($model->title.' '.$model->summary);
        $matched = $hit['matched_terms'] ?: array_values(array_filter($terms, fn ($t) => Str::contains($haystack, $t)));

        return [
            'type' => $hit['type'],
            'slug' => $model->slug,
            'title' => $model->title,
            'summary' => $model->summary,
            'score' => $hit['score'],
            'matched_terms' => $matched,
            'cover' => $model->coverImage()?->url,
            'url' => localized_route($routeName, [$param => $model->slug]),
        ];
    }

    private function terms(string $q): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/\s+/u', Str::lower($q)) ?: [],
            fn ($t) => mb_strlen($t) >= 2
        )));
    }

    private function log(string $q, string $locale, ?User $user, int $count, string $engine, int $duration): void
    {
        try {
            SearchLog::create([
                'user_id' => $user?->id,
                'locale' => $locale,
                'query' => Str::limit($q, 250, ''),
                'result_count' => min($count, 65535),
                'engine' => $engine,
                'duration_ms' => $duration,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
