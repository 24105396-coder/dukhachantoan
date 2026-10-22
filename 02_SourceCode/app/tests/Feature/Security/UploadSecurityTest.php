<?php

namespace Tests\Feature\Security;

use App\Http\Resources\ContributionItemResource;
use App\Models\Contribution;
use App\Models\HeritageSite;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * H2: upload media kiểm MIME thật/magic bytes, đuôi cố định, tên ngẫu nhiên, chặn SVG/HTML.
 * L3: tệp đính kèm đóng góp ở disk private, tải qua route có kiểm quyền.
 * Dùng UploadedFile thật (không fake) để getMimeType() đọc nội dung tệp bằng finfo.
 */
class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const HTML = '<html><body><script>fetch("/vi/quan-tri/nguoi-dung/5")</script></body></html>';
    private const SVG = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>';

    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, $content);
        $this->tmp[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function upload(string $type, UploadedFile $file)
    {
        $site = HeritageSite::factory()->create();

        return $this->actingAs($this->admin())->postJson('/vi/quan-tri/media', [
            'subject_type' => 'site', 'subject_id' => $site->id, 'media_type' => $type, 'file' => $file,
            'caption_vi' => 'Chú thích', 'source_name' => 'Nguồn thử', 'license' => 'simulated',
        ]);
    }

    public function test_mp3_containing_html_is_rejected(): void
    {
        Storage::fake('public');

        $this->upload('audio', $this->realFile('evil.mp3', self::HTML))
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(0, MediaAsset::count());
    }

    public function test_real_mp3_is_accepted_and_saved_as_mp3(): void
    {
        Storage::fake('public');
        $mp3 = "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x64".str_repeat("\x00", 413), 3);

        $this->upload('audio', $this->realFile('thuyet-minh.mp3', $mp3))->assertRedirect();

        $media = MediaAsset::firstOrFail();
        $this->assertMatchesRegularExpression('#^media/audio/[a-z0-9]{40}\.mp3$#', $media->path);
        $this->assertSame('audio/mpeg', $media->mime);
    }

    public function test_glb_containing_html_or_svg_is_rejected(): void
    {
        Storage::fake('public');

        $this->upload('model3d', $this->realFile('evil.glb', self::HTML))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload('model3d', $this->realFile('evil.glb', self::SVG))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(0, MediaAsset::count());
    }

    public function test_svg_image_upload_is_rejected_even_when_renamed(): void
    {
        Storage::fake('public');

        $this->upload('image', $this->realFile('logo.svg', self::SVG))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload('image', $this->realFile('logo.png', self::SVG))->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_valid_glb_is_stored_with_random_name_and_fixed_extension(): void
    {
        Storage::fake('public');
        $glb = 'glTF'.pack('V', 2).pack('V', 20).str_repeat("\0", 8);

        $this->upload('model3d', $this->realFile('Model Name.glb', $glb))->assertRedirect();

        $media = MediaAsset::firstOrFail();
        $this->assertMatchesRegularExpression('#^media/model3d/[a-z0-9]{40}\.glb$#', $media->path);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_image_extension_follows_real_mime_not_client_name(): void
    {
        Storage::fake('public');
        $png = UploadedFile::fake()->image('anh.png', 40, 30);

        // Ảnh PNG thật nhưng đặt tên .jpg: lưu với đuôi png theo MIME thật
        $this->upload('image', $this->realFile('anh.jpg', (string) file_get_contents($png->getRealPath())))->assertRedirect();

        $this->assertMatchesRegularExpression('#^media/image/[a-z0-9]{40}\.png$#', MediaAsset::firstOrFail()->path);
    }

    public function test_pdf_document_requires_pdf_content(): void
    {
        Storage::fake('public');

        $this->upload('document', $this->realFile('tai-lieu.pdf', self::HTML))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload('document', $this->realFile('tai-lieu.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"))->assertRedirect();
        $this->assertStringEndsWith('.pdf', MediaAsset::firstOrFail()->path);
    }

    public function test_contribution_attachment_is_private_and_served_only_to_owner_or_staff(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->member();
        $png = UploadedFile::fake()->image('anh.png', 20, 20);

        $this->actingAs($owner)->post('/vi/dong-gop', [
            'contribution_type' => 'story', 'locale' => 'vi', 'title' => 'Ảnh đình làng',
            'body' => 'Ảnh chụp đình làng năm 1990 do gia đình tôi lưu giữ.',
            'attachment' => $png,
        ])->assertSessionHasNoErrors();

        $c = Contribution::firstOrFail();
        Storage::disk('local')->assertExists($c->attachment_path);
        Storage::disk('public')->assertMissing($c->attachment_path);
        $url = ContributionItemResource::signedAttachmentUrl($c->id);

        $this->actingAs($owner)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->manager())->get($url)->assertOk();
        $this->actingAs($this->member())->get($url)->assertForbidden();
        auth()->logout();
        $this->get($url)->assertRedirect();
    }

    public function test_contribution_attachment_rejects_html_disguised_as_pdf(): void
    {
        Storage::fake('local');

        $this->actingAs($this->member())->postJson('/vi/dong-gop', [
            'contribution_type' => 'story', 'locale' => 'vi', 'title' => 'Tài liệu cổ',
            'body' => 'Bản chụp tài liệu cổ về lịch sử làng tôi.',
            'attachment' => $this->realFile('tai-lieu.pdf', self::HTML),
        ])->assertUnprocessable()->assertJsonValidationErrors('attachment');
    }
}
