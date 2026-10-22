<?php

namespace Tests\Feature;

use App\Http\Resources\ContributionItemResource;
use App\Models\AuditLog;
use App\Models\Contribution;
use App\Models\TourRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Truy cập trái phép (sai vai trò, Policy từ chối, chữ ký URL sai) phải để lại dấu vết trong log + audit_logs. */
class AccessDeniedAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_denied_admin_is_logged_and_audited(): void
    {
        Log::spy();
        $member = $this->member();

        $this->actingAs($member)->get('/vi/quan-tri')->assertForbidden();

        $log = AuditLog::where('action', 'access.denied')->sole();
        $this->assertSame($member->id, $log->user_id);
        $this->assertSame('member', $log->meta['role']);
        $this->assertSame('/vi/quan-tri', $log->meta['path']);
        $this->assertSame('role', $log->meta['reason']);
        $this->assertNotEmpty($log->ip);
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => $msg === 'Truy cập trái phép'
            && $ctx['user_id'] === $member->id && $ctx['path'] === '/vi/quan-tri')->once();
    }

    public function test_member_denied_admin_sites_is_audited(): void
    {
        $this->actingAs($this->member())->get('/vi/quan-tri/di-san')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['action' => 'access.denied']);
    }

    public function test_policy_denial_is_audited(): void
    {
        $route = TourRoute::factory()->ownedBy($this->contributor())->create();
        $other = $this->contributor();

        $this->actingAs($other)->get("/vi/quan-tri/tuyen-tham-quan/{$route->slug}/sua")->assertForbidden();

        $log = AuditLog::where('action', 'access.denied')->sole();
        $this->assertSame($other->id, $log->user_id);
        $this->assertSame('policy', $log->meta['reason']);
    }

    public function test_invalid_signature_is_audited_without_query_string(): void
    {
        $owner = $this->member();
        $c = Contribution::create([
            'user_id' => $owner->id, 'contribution_type' => 'story', 'locale' => 'vi',
            'title' => 'Ảnh', 'body' => 'Nội dung đóng góp mẫu.', 'attachment_path' => 'contributions/x.png',
        ]);

        $this->actingAs($owner)->get(ContributionItemResource::signedAttachmentUrl($c->id).'x')->assertForbidden();

        $log = AuditLog::where('action', 'access.denied')->sole();
        $this->assertSame('signature', $log->meta['reason']);
        $this->assertStringNotContainsString('signature=', $log->meta['path']);
    }

    public function test_allowed_access_writes_no_denial(): void
    {
        $this->actingAs($this->admin())->get('/vi/quan-tri')->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'access.denied']);
    }
}
