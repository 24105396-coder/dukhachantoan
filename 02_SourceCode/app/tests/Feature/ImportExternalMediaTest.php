<?php

namespace Tests\Feature;

use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\MediaAsset;
use App\Models\TourRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lệnh app:import-external-media gắn ảnh Wikimedia Commons làm ảnh bìa, giữ ảnh cũ, idempotent. */
class ImportExternalMediaTest extends TestCase
{
    use RefreshDatabase;

    private function jsonFile(array $items): string
    {
        $path = storage_path('app/test_media_external.json');
        file_put_contents($path, json_encode(['fetched_at' => '2026-10-19', 'items' => $items], JSON_UNESCAPED_UNICODE));

        return 'storage/app/test_media_external.json';
    }

    public function test_sets_wikimedia_image_as_cover_with_provenance_and_keeps_old_media(): void
    {
        $site = HeritageSite::factory()->create(['slug' => 'chua-mot-cot']);
        $old = MediaAsset::create(['subject_type' => 'site', 'subject_id' => $site->id, 'media_type' => 'image',
            'path' => 'media/site/x.svg', 'sort_order' => 1, 'is_cover' => true, 'source_name' => 'Nhóm', 'license' => 'simulated']);
        $site->forceFill(['cover_media_id' => $old->id])->save();
        $file = $this->jsonFile([[
            'type' => 'site', 'slug' => 'chua-mot-cot', 'title' => 'Chùa Một Cột', 'wikipedia' => 'Chùa Một Cột',
            'file' => 'One_Pillar_Pagoda.jpg', 'url' => 'https://upload.wikimedia.org/x/One_Pillar_Pagoda.jpg', 'width' => 1280, 'height' => 853,
            'mime' => 'image/jpeg', 'source_url' => 'https://commons.wikimedia.org/wiki/File:One_Pillar_Pagoda.jpg',
            'author' => 'Nguyễn Văn A', 'license' => 'cc_by_sa', 'license_name' => 'CC BY-SA 4.0', 'captured_at' => '2019-05-01',
        ], ['type' => 'figure', 'slug' => 'khong-ton-tai', 'url' => 'https://example.com/a.jpg']]);

        $this->artisan('app:import-external-media', ['--file' => $file])->assertSuccessful();
        $this->artisan('app:import-external-media', ['--file' => $file])->assertSuccessful();

        $cover = MediaAsset::where('external_url', 'https://upload.wikimedia.org/x/One_Pillar_Pagoda.jpg')->get();
        $this->assertCount(1, $cover, 'chạy hai lần không tạo trùng');
        $cover = $cover->first();
        $this->assertSame($cover->id, $site->fresh()->cover_media_id);
        $this->assertTrue($cover->is_cover);
        $this->assertFalse($old->fresh()->is_cover);
        $this->assertSame('cc_by_sa', $cover->license);
        $this->assertSame('Nguyễn Văn A', $cover->author);
        $this->assertStringContainsString('CC BY-SA 4.0', $cover->provenance_note);
        $this->assertSame('Chùa Một Cột', $cover->caption('vi')?->caption);
        $this->assertSame(2, $site->media()->count());
        $this->assertSame(0, HistoricalFigure::count());
    }

    public function test_missing_file_is_skipped_gracefully(): void
    {
        $this->artisan('app:import-external-media', ['--file' => 'database/data/khong-co.json'])->assertSuccessful();
        $this->assertSame(0, MediaAsset::count());
    }

    public function test_route_cover_reuses_first_stop_real_image(): void
    {
        $site = HeritageSite::factory()->create(['slug' => 'van-mieu']);
        $route = TourRoute::factory()->create();
        $route->stops()->create(['site_id' => $site->id, 'stop_order' => 1, 'dwell_minutes' => 60]);
        $file = $this->jsonFile([[
            'type' => 'site', 'slug' => 'van-mieu', 'title' => 'Văn Miếu', 'file' => 'VM.jpg', 'url' => 'https://upload.wikimedia.org/x/VM.jpg',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:VM.jpg', 'author' => 'B', 'license' => 'cc_by', 'license_name' => 'CC BY 4.0',
        ]]);

        $this->artisan('app:import-external-media', ['--file' => $file])->assertSuccessful();
        $this->artisan('app:import-external-media', ['--file' => $file])->assertSuccessful();

        $cover = MediaAsset::where('subject_type', 'route')->where('subject_id', $route->id)->get();
        $this->assertCount(1, $cover);
        $this->assertSame('https://upload.wikimedia.org/x/VM.jpg', $cover->first()->external_url);
        $this->assertSame('cc_by', $cover->first()->license);
        $this->assertSame($cover->first()->id, $route->fresh()->cover_media_id);
        $this->assertSame('Văn Miếu', $cover->first()->caption('vi')?->caption);
    }
}
