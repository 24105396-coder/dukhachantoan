<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Phân quyền 4 vai trò ở máy chủ: admin, agency, partner, traveler (+ khách vãng lai). */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ONLY = ['/vi/quan-tri/nguoi-dung', '/vi/quan-tri/nhat-ky', '/vi/quan-tri/sao-luu'];

    public function test_guest_is_redirected_to_login_from_admin(): void
    {
        $this->get('/vi/quan-tri')->assertRedirect('/vi/dang-nhap');
        $this->assertSame(url('/vi/quan-tri'), session('url.intended'));

        $this->get('/en/quan-tri/nguoi-dung')->assertRedirect('/en/dang-nhap');
        $this->post('/vi/quan-tri/sao-luu')->assertRedirect('/vi/dang-nhap');
    }

    public function test_guest_returns_to_admin_after_login(): void
    {
        $admin = $this->admin();
        $this->get('/vi/quan-tri')->assertRedirect('/vi/dang-nhap');

        $this->post('/vi/dang-nhap', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(url('/vi/quan-tri'));
    }

    public function test_traveler_cannot_access_admin_area(): void
    {
        $traveler = $this->traveler();
        $this->actingAs($traveler)->get('/vi/quan-tri')->assertForbidden();
        foreach (self::ADMIN_ONLY as $url) {
            $this->actingAs($traveler)->get($url)->assertForbidden();
        }
    }

    public function test_partner_cannot_access_admin_area(): void
    {
        $partner = $this->partner();
        $this->actingAs($partner)->get('/vi/quan-tri')->assertForbidden();
        $this->actingAs($partner)->get('/vi/quan-tri/nguoi-dung')->assertForbidden();
    }

    public function test_agency_can_open_dashboard_scoped_to_own_province(): void
    {
        $agency = $this->agency('KH');

        $this->actingAs($agency)->get('/vi/quan-tri')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard')
                ->where('scope.province.code', 'KH')
                ->has('kpis.alerts_by_severity', 4)
                ->has('kpis.notifications_24h')
                ->has('queueHealth.queued')
                ->where('zonesGeo.type', 'FeatureCollection'));
    }

    public function test_agency_cannot_manage_users_audit_or_backup(): void
    {
        $agency = $this->agency();
        $target = $this->traveler();

        foreach (self::ADMIN_ONLY as $url) {
            $this->actingAs($agency)->get($url)->assertForbidden();
        }
        $this->actingAs($agency)->post('/vi/quan-tri/sao-luu')->assertForbidden();
        $this->actingAs($agency)->patch("/vi/quan-tri/nguoi-dung/{$target->id}", ['role_id' => $agency->role_id, 'is_active' => true])
            ->assertForbidden();
        $this->assertSame('traveler', $target->fresh()->role->code);
    }

    public function test_admin_can_access_every_admin_page(): void
    {
        $admin = $this->admin();
        foreach (array_merge(['/vi/quan-tri'], self::ADMIN_ONLY) as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_can_promote_user_to_agency_with_province(): void
    {
        $admin = $this->admin();
        $target = $this->traveler();
        $agencyRoleId = User::factory()->agency()->create()->role_id;

        $this->actingAs($admin)->patch("/vi/quan-tri/nguoi-dung/{$target->id}", [
            'role_id' => $agencyRoleId, 'is_active' => true, 'province_code' => 'KH',
        ])->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame(Role::AGENCY, $target->role->code);
        $this->assertSame('KH', $target->province->code);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user.update', 'subject_type' => 'user', 'subject_id' => $target->id]);
    }

    public function test_admin_user_update_validates_input(): void
    {
        $admin = $this->admin();
        $target = $this->traveler();

        $this->actingAs($admin)->patch("/vi/quan-tri/nguoi-dung/{$target->id}", [
            'role_id' => 999999, 'is_active' => 'maybe', 'province_code' => 'XX',
        ])->assertSessionHasErrors(['role_id', 'is_active', 'province_code']);
    }

    public function test_admin_cannot_demote_self(): void
    {
        $admin = $this->admin();
        $travelerRoleId = $this->traveler()->role_id;

        $this->actingAs($admin)->patch("/vi/quan-tri/nguoi-dung/{$admin->id}", ['role_id' => $travelerRoleId, 'is_active' => true])
            ->assertSessionHasErrors('role_id');
        $this->assertSame('admin', $admin->fresh()->role->code);
    }

    public function test_inactive_staff_is_blocked(): void
    {
        $user = User::factory()->admin()->inactive()->create();

        // Tài khoản bị khóa bị đăng xuất ngay ở mọi route web, quay về trang đăng nhập
        $this->actingAs($user)->get('/vi/quan-tri')->assertRedirect(localized_route('login', ['locale' => 'vi']));
        $this->assertGuest();
    }

    public function test_every_role_can_open_own_account_page(): void
    {
        foreach ([$this->admin(), $this->agency(), $this->partner(), $this->traveler()] as $user) {
            $this->actingAs($user)->get('/vi/tai-khoan')->assertOk();
        }
    }
}
