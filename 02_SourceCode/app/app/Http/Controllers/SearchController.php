<?php

namespace App\Http\Controllers;

use App\Http\Resources\TagItemResource;
use App\Models\Tag;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): Response
    {
        $query = $this->filters($request, ['q'])['q'] ?? '';
        $locale = app()->getLocale();
        $result = $query !== '' ? $search->search($query, $locale, $request->user()) : ['engine' => null, 'results' => [], 'durationMs' => 0];

        return Inertia::render('Search/Index', [
            'query' => $query,
            'results' => $result['results'],
            'engine' => $result['engine'] ?? 'semantic',
            'durationMs' => $result['durationMs'],
            'suggestedTags' => $this->suggestedTags($query),
            'popularQueries' => $search->popularQueries($locale),
        ]);
    }

    private function suggestedTags(string $query): array
    {
        $tags = Tag::query()->with('translations')
            ->when($query !== '', function ($q) use ($query) {
                $like = like_contains($query);
                $q->whereHas('translations', fn ($t) => $t->where('name', 'like', $like));
            })
            ->orderByDesc('usage_count')->limit(8)->get();

        if ($tags->isEmpty()) {
            $tags = Tag::query()->with('translations')->where('tag_group', 'chu_de')->orderByDesc('usage_count')->limit(8)->get();
        }

        return TagItemResource::list($tags);
    }
}
