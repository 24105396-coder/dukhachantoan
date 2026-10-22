<?php

namespace Tests\Feature;

use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncViewCountsTest extends TestCase
{
    use RefreshDatabase;

    private function recordView(string $type, int $id, ?string $countedAt = null): void
    {
        PageView::create([
            'subject_type' => $type, 'subject_id' => $id, 'locale' => 'vi',
            'viewed_on' => now()->toDateString(), 'created_at' => now(), 'counted_at' => $countedAt,
        ]);
    }

    public function test_pending_views_are_added_once_per_subject(): void
    {
        $site = HeritageSite::factory()->create(['view_count' => 10]);
        $artifact = Artifact::factory()->create(['view_count' => 0]);
        foreach (range(1, 3) as $_) {
            $this->recordView('site', $site->id);
        }
        $this->recordView('artifact', $artifact->id);
        $this->recordView('site', $site->id, now()->subDay()->toDateTimeString()); // đã đếm trước đó
        $this->recordView('route', 999); // loại không có view_count → bỏ qua, vẫn đánh dấu

        $this->artisan('app:sync-view-counts')->assertSuccessful();

        $this->assertSame(13, $site->fresh()->view_count);
        $this->assertSame(1, $artifact->fresh()->view_count);
        $this->assertSame(0, PageView::whereNull('counted_at')->count());

        $this->artisan('app:sync-view-counts')->assertSuccessful();
        $this->assertSame(13, $site->fresh()->view_count);
    }

    public function test_concurrent_visitors_do_not_update_site_row_per_view(): void
    {
        $site = HeritageSite::factory()->create();
        $updatedAt = $site->updated_at;

        $this->get("/vi/di-san/{$site->slug}")->assertOk();

        $this->assertSame(1, PageView::whereNull('counted_at')->count());
        $this->assertSame(0, $site->fresh()->view_count);
        $this->assertEquals($updatedAt, $site->fresh()->updated_at);
    }
}
