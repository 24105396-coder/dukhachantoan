<?php

namespace Tests\Feature;

use App\Models\HeritageSite;
use App\Models\MediaAsset;
use App\Models\TourRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Sửa thông tin một tư liệu đã có: chú thích, nguồn gốc, ảnh bìa, thay tệp. */
class AdminMediaUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function mediaFor(HeritageSite $site, array $extra = []): MediaAsset
    {
        $media = MediaAsset::create(array_merge([
            'subject_type' => 'site', 'subject_id' => $site->id, 'media_type' => 'image',
            'path' => 'media/image/cu.jpg', 'mime' => 'image/jpeg', 'size_bytes' => 10, 'width' => 10, 'height' => 10,
            'sort_order' => 1, 'is_cover' => false, 'source_name' => 'Nguồn cũ', 'license' => 'cc_by',
        ], $extra));
        $media->captions()->create(['locale' => 'vi', 'caption' => 'Chú thích cũ']);

        return $media;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'caption_vi' => 'Chú thích mới', 'caption_en' => 'New caption',
            'source_name' => 'Nguồn mới', 'license' => 'cc_by_sa', 'author' => 'Tác giả', 'is_cover' => false,
        ], $override);
    }

    public function test_updates_metadata_and_captions_without_touching_file(): void
    {
        Storage::fake('public');
        $site = HeritageSite::factory()->create();
        $media = $this->mediaFor($site);

        $this->actingAs($this->manager())->put("/vi/quan-tri/media/{$media->id}", $this->payload())
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $media->refresh();
        $this->assertSame('Nguồn mới', $media->source_name);
        $this->assertSame('cc_by_sa', $media->license);
        $this->assertSame('media/image/cu.jpg', $media->path);
        $this->assertSame('Chú thích mới', $media->caption('vi')?->caption);
        $this->assertSame('New caption', $media->caption('en')?->caption);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.media.update']);
    }

    public function test_setting_cover_updates_subject_and_unsets_other_covers(): void
    {
        Storage::fake('public');
        $site = HeritageSite::factory()->create();
        $old = $this->mediaFor($site, ['is_cover' => true, 'sort_order' => 1]);
        $site->forceFill(['cover_media_id' => $old->id])->save();
        $new = $this->mediaFor($site, ['sort_order' => 2]);

        $this->actingAs($this->admin())->put("/vi/quan-tri/media/{$new->id}", $this->payload(['is_cover' => true]))
            ->assertSessionHasNoErrors();

        $this->assertSame($new->id, $site->fresh()->cover_media_id);
        $this->assertFalse($old->fresh()->is_cover);
        $this->assertTrue($new->fresh()->is_cover);
    }

    public function test_unsetting_cover_clears_subject_cover(): void
    {
        $site = HeritageSite::factory()->create();
        $media = $this->mediaFor($site, ['is_cover' => true]);
        $site->forceFill(['cover_media_id' => $media->id])->save();

        $this->actingAs($this->admin())->put("/vi/quan-tri/media/{$media->id}", $this->payload(['is_cover' => false]))
            ->assertSessionHasNoErrors();

        $this->assertNull($site->fresh()->cover_media_id);
    }

    public function test_replacing_file_deletes_old_and_stores_new(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/image/cu.jpg', 'x');
        $site = HeritageSite::factory()->create();
        $media = $this->mediaFor($site);

        $this->actingAs($this->admin())->put("/vi/quan-tri/media/{$media->id}", $this->payload([
            'file' => UploadedFile::fake()->image('moi.png', 640, 480),
        ]))->assertSessionHasNoErrors();

        $media->refresh();
        Storage::disk('public')->assertMissing('media/image/cu.jpg');
        Storage::disk('public')->assertExists($media->path);
        $this->assertSame(640, $media->width);
        $this->assertSame('image/png', $media->mime);
    }

    public function test_rejects_svg_replacement_and_requires_caption(): void
    {
        Storage::fake('public');
        $site = HeritageSite::factory()->create();
        $media = $this->mediaFor($site);

        $this->actingAs($this->admin())->put("/vi/quan-tri/media/{$media->id}", $this->payload([
            'caption_vi' => '', 'file' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ]))->assertSessionHasErrors(['caption_vi', 'file']);
    }

    public function test_member_cannot_update_media(): void
    {
        $site = HeritageSite::factory()->create();
        $media = $this->mediaFor($site);

        $this->actingAs($this->member())->put("/vi/quan-tri/media/{$media->id}", $this->payload())->assertForbidden();
        $this->assertSame('Nguồn cũ', $media->fresh()->source_name);
    }

    public function test_tour_edit_form_exposes_media_for_editing(): void
    {
        $route = TourRoute::factory()->create();
        $media = MediaAsset::create([
            'subject_type' => 'route', 'subject_id' => $route->id, 'media_type' => 'image', 'path' => 'media/image/t.jpg',
            'sort_order' => 1, 'is_cover' => true, 'source_name' => 'Nguồn', 'license' => 'cc_by',
        ]);
        $media->captions()->create(['locale' => 'vi', 'caption' => 'Ảnh tuyến']);

        $this->actingAs($this->manager())->get("/vi/quan-tri/tuyen-tham-quan/{$route->slug}/sua")
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Tours/Form')
                ->where('route.media.0.id', $media->id)
                ->where('route.media.0.caption_vi', 'Ảnh tuyến')
                ->has('enums.licenses'));
    }
}
