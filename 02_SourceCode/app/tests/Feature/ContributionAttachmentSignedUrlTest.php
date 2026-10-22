<?php

namespace Tests\Feature;

use App\Http\Resources\ContributionItemResource;
use App\Models\Contribution;
use App\Models\User;
use App\Support\UrlToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Mục 2.2 Đề thi / Mục 6.6 TLKT (mã hóa tham số URL): tệp đính kèm đóng góp chỉ tải được qua URL có id mã hóa
 * và chữ ký 60 phút,
 * chữ ký không thay thế Policy (vẫn phải là chủ sở hữu hoặc staff).
 */
class ContributionAttachmentSignedUrlTest extends TestCase
{
    use RefreshDatabase;

    private function contributionWithAttachment(User $owner): Contribution
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('contributions/tep-mau.png', 'png-bytes');

        return Contribution::create([
            'user_id' => $owner->id, 'contribution_type' => 'story', 'locale' => 'vi',
            'title' => 'Ảnh đình làng', 'body' => 'Ảnh chụp đình làng năm 1990.',
            'attachment_path' => 'contributions/tep-mau.png', 'status' => 'pending',
        ]);
    }

    /** Lấy attachment_url đúng như frontend nhận từ props Inertia. */
    private function attachmentUrlFromProps(User $viewer, string $url, string $prop): string
    {
        $found = null;
        $this->actingAs($viewer)->get($url)->assertOk()
            ->assertInertia(function (Assert $p) use ($prop, &$found) {
                $p->has($prop);
                $found = data_get($p->toArray()['props'], $prop);
            });
        $this->assertIsString($found);

        return $found;
    }

    public function test_attachment_url_in_props_is_signed_and_expires(): void
    {
        $owner = $this->member();
        $c = $this->contributionWithAttachment($owner);

        $urls = [
            $this->attachmentUrlFromProps($owner, "/vi/dong-gop/{$c->id}", 'contribution.attachment_url'),
            $this->attachmentUrlFromProps($this->manager(), "/vi/quan-tri/kiem-duyet/{$c->id}", 'contribution.attachment_url'),
            $this->attachmentUrlFromProps($this->manager(), '/vi/quan-tri/kiem-duyet', 'contributions.data.0.attachment_url'),
        ];

        foreach ($urls as $url) {
            $this->assertStringStartsWith('/vi/dong-gop/tep-dinh-kem/', $url);
            $this->assertStringNotContainsString("/{$c->id}/", $url);
            $this->assertStringNotContainsString("/{$c->id}?", $url);
            $this->assertStringContainsString('expires=', $url);
            $this->assertStringContainsString('signature=', $url);
        }
    }

    public function test_signed_url_with_permission_returns_file(): void
    {
        $owner = $this->member();
        $c = $this->contributionWithAttachment($owner);
        $url = ContributionItemResource::signedAttachmentUrl($c->id);

        $this->actingAs($owner)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->manager())->get($url)->assertOk();
    }

    public function test_unsigned_or_tampered_url_is_forbidden(): void
    {
        $owner = $this->member();
        $c = $this->contributionWithAttachment($owner);
        $other = $this->contributionWithAttachment($owner);

        $signed = ContributionItemResource::signedAttachmentUrl($c->id);
        $unsigned = strtok($signed, '?');
        $this->actingAs($owner)->get($unsigned)->assertForbidden();
        $this->actingAs($owner)->get($signed.'x')->assertForbidden();
        // Thay token của tệp khác nhưng giữ chữ ký cũ → chữ ký không khớp
        $otherToken = basename(strtok(ContributionItemResource::signedAttachmentUrl($other->id), '?'));
        $swapped = str_replace(basename($unsigned), $otherToken, $signed);
        $this->actingAs($owner)->get($swapped)->assertForbidden();
    }

    public function test_signed_url_with_invalid_token_is_not_found(): void
    {
        $owner = $this->member();
        $this->contributionWithAttachment($owner);

        // Chữ ký hợp lệ nhưng token không giải mã được → 404
        $url = URL::temporarySignedRoute('contributions.attachment', now()->addMinutes(5), ['locale' => 'vi', 'token' => 'khong-phai-token'], false);
        $this->actingAs($owner)->get($url)->assertNotFound();

        // Token của loại tài nguyên khác (đặt lịch) cũng bị từ chối
        $foreign = URL::temporarySignedRoute('contributions.attachment', now()->addMinutes(5), ['locale' => 'vi', 'token' => UrlToken::encode('booking', 1)], false);
        $this->actingAs($owner)->get($foreign)->assertNotFound();
    }

    public function test_expired_signed_url_is_forbidden(): void
    {
        $owner = $this->member();
        $c = $this->contributionWithAttachment($owner);
        $url = ContributionItemResource::signedAttachmentUrl($c->id);

        $this->travel(ContributionItemResource::ATTACHMENT_URL_TTL + 1)->minutes();

        $this->actingAs($owner)->get($url)->assertForbidden();
    }

    public function test_valid_signature_does_not_bypass_policy(): void
    {
        $c = $this->contributionWithAttachment($this->member());
        $url = ContributionItemResource::signedAttachmentUrl($c->id);

        $this->actingAs($this->member())->get($url)->assertForbidden();
    }
}
