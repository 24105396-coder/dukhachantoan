<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Truy cập trái phép (sai vai trò) phải để lại dấu vết trong log + audit_logs. */
class AccessDeniedAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_traveler_denied_admin_is_logged_and_audited(): void
    {
        Log::spy();
        $traveler = $this->traveler();

        $this->actingAs($traveler)->get('/vi/quan-tri')->assertForbidden();

        $log = AuditLog::where('action', 'access.denied')->sole();
        $this->assertSame($traveler->id, $log->user_id);
        $this->assertSame('traveler', $log->meta['role']);
        $this->assertSame('/vi/quan-tri', $log->meta['path']);
        $this->assertSame('role', $log->meta['reason']);
        $this->assertNotEmpty($log->ip);
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => $msg === 'Truy cập trái phép'
            && $ctx['user_id'] === $traveler->id && $ctx['path'] === '/vi/quan-tri')->once();
    }

    public function test_agency_denied_admin_only_page_is_audited(): void
    {
        $agency = $this->agency();

        $this->actingAs($agency)->get('/vi/quan-tri/nhat-ky')->assertForbidden();

        $log = AuditLog::where('action', 'access.denied')->sole();
        $this->assertSame('agency', $log->meta['role']);
        $this->assertSame(['admin'], $log->meta['required']);
    }

    public function test_allowed_access_writes_no_denial(): void
    {
        $this->actingAs($this->admin())->get('/vi/quan-tri')->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'access.denied']);
    }
}
