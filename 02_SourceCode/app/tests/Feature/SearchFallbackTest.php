<?php

namespace Tests\Feature;

use App\Models\HeritageSite;
use App\Models\Interpretation;
use App\Models\SearchLog;
use App\Services\AnalyticsClient;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchFallbackTest extends TestCase
{
    use RefreshDatabase;

    private HeritageSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->site = HeritageSite::factory()->create(['slug' => 'hoang-thanh-thang-long']);
        Interpretation::query()->where('subject_id', $this->site->id)->where('locale', 'vi')
            ->update(['title' => 'Hoàng thành Thăng Long', 'summary' => 'Trung tâm quyền lực các triều đại']);
    }

    public function test_falls_back_to_database_when_analytics_returns_error(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'boom'], 500)]);

        $result = app(SearchService::class)->search('Thăng Long', 'vi');

        $this->assertSame('fallback', $result['engine']);
        $this->assertSame('hoang-thanh-thang-long', $result['results'][0]['slug']);
        $this->assertSame('site', $result['results'][0]['type']);
        $this->assertStringEndsWith('/vi/di-san/hoang-thanh-thang-long', $result['results'][0]['url']);
        $this->assertDatabaseHas('search_logs', ['query' => 'Thăng Long', 'engine' => 'fallback', 'result_count' => 1]);
    }

    public function test_falls_back_when_analytics_times_out(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->get('/vi/tim-kiem?q=Hoàng thành')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Search/Index')
                ->where('engine', 'fallback')
                ->where('query', 'Hoàng thành')
                ->has('results', 1)
                ->has('suggestedTags')->has('popularQueries'));
        $this->assertFalse(app(AnalyticsClient::class)->isUp());
    }

    public function test_uses_semantic_engine_when_analytics_is_up(): void
    {
        Http::fake([
            '*/search' => Http::response(['engine' => 'tfidf', 'took_ms' => 3, 'results' => [
                ['subject_type' => 'site', 'subject_id' => $this->site->id, 'slug' => $this->site->slug, 'score' => 0.87, 'matched_terms' => ['thăng', 'long']],
                ['subject_type' => 'site', 'subject_id' => 999999, 'slug' => 'khong-ton-tai', 'score' => 0.5, 'matched_terms' => []],
            ]]),
        ]);

        $result = app(SearchService::class)->search('kinh đô Thăng Long', 'vi');

        $this->assertSame('semantic', $result['engine']);
        $this->assertCount(1, $result['results']);
        $this->assertSame(0.87, $result['results'][0]['score']);
        $this->assertSame(['thăng', 'long'], $result['results'][0]['matched_terms']);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Analytics-Token', 'test-token') && $request['q'] === 'kinh đô Thăng Long');
        $this->assertSame('semantic', SearchLog::first()->engine);
    }

    public function test_draft_sites_are_not_returned_by_fallback(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        $this->site->update(['status' => 'draft']);

        $this->assertSame([], app(SearchService::class)->search('Thăng Long', 'vi')['results']);
    }

    public function test_like_wildcards_in_query_are_escaped(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);

        $this->assertSame([], app(SearchService::class)->search('%%%', 'vi')['results']);
        $this->assertSame([], app(SearchService::class)->search("' OR 1=1 --", 'vi')['results']);
    }

    public function test_api_search_reports_engine(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        $this->getJson('/api/v1/search?q=Thăng Long&locale=vi')->assertOk()
            ->assertJson(['engine' => 'fallback'])
            ->assertJsonPath('data.0.slug', 'hoang-thanh-thang-long');
    }

    public function test_empty_semantic_results_use_fulltext_fallback(): void
    {
        // Chỉ mục Python vừa khởi động còn rỗng: trả 200 nhưng không có kết quả
        Http::fake(['*/search' => Http::response(['engine' => 'tfidf', 'took_ms' => 1, 'results' => []])]);

        $result = app(SearchService::class)->search('Thăng Long', 'vi');

        $this->assertSame('fallback', $result['engine']);
        $this->assertSame('hoang-thanh-thang-long', $result['results'][0]['slug']);
        $this->assertDatabaseHas('search_logs', ['query' => 'Thăng Long', 'engine' => 'fallback']);
    }

    public function test_malformed_semantic_payload_uses_fallback(): void
    {
        Http::fake(['*/search' => Http::response(['unexpected' => true])]);

        $this->getJson('/api/v1/search?q=Thăng Long&locale=vi')->assertOk()->assertJsonPath('engine', 'fallback');
    }
}
