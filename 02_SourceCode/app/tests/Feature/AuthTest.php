<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_inertia_component(): void
    {
        $this->get('/vi/dang-nhap')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('locale', 'vi')->where('auth.user', null));
    }

    public function test_user_can_login_and_session_is_regenerated(): void
    {
        $user = User::factory()->traveler()->create(['email' => 'user@dukhach.test']);
        $this->get('/vi/dang-nhap');
        $oldSession = session()->getId();

        $response = $this->post('/vi/dang-nhap', ['email' => 'USER@dukhach.test', 'password' => 'password']);

        $response->assertRedirect(localized_route('account.edit', ['locale' => 'vi']));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_returns_error_on_email_field(): void
    {
        User::factory()->create(['email' => 'user@dukhach.test']);

        $this->from('/vi/dang-nhap')->post('/vi/dang-nhap', ['email' => 'user@dukhach.test', 'password' => 'sai-mat-khau'])
            ->assertRedirect('/vi/dang-nhap')
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', 'auth.login_failed')->count());
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'locked@dukhach.test']);

        $this->post('/vi/dang-nhap', ['email' => 'locked@dukhach.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'user@dukhach.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/vi/dang-nhap', ['email' => 'user@dukhach.test', 'password' => 'wrong'.$i]);
        }

        $this->post('/vi/dang-nhap', ['email' => 'user@dukhach.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('quá nhiều lần', session('errors')->first('email'));
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/vi/dang-xuat')->assertRedirect(localized_route('home', ['locale' => 'vi']));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_staff_login_redirects_to_admin_dashboard(): void
    {
        $agency = User::factory()->agency()->create(['email' => 'agency@dukhach.test']);

        $this->post('/vi/dang-nhap', ['email' => 'agency@dukhach.test', 'password' => 'password'])
            ->assertRedirect(localized_route('admin.dashboard', ['locale' => 'vi']));
        $this->assertAuthenticatedAs($agency);
    }

    public function test_register_creates_traveler_and_ignores_role_input(): void
    {
        $this->post('/vi/dang-ky', [
            'name' => 'Nguyễn Văn A',
            'email' => 'moi@dukhach.test',
            'password' => 'MatKhau123',
            'password_confirmation' => 'MatKhau123',
            'nationality' => 'gb',
            'preferred_locale' => 'en',
            'role_id' => 1,
            'role' => 'admin',
        ])->assertRedirect(localized_route('account.edit', ['locale' => 'en']));

        $user = User::where('email', 'moi@dukhach.test')->firstOrFail();
        $this->assertSame('traveler', $user->role->code);
        $this->assertSame('GB', $user->nationality);
        $this->assertSame('en', $user->locale);
        $this->assertNotSame('MatKhau123', $user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())->get('/vi/dang-nhap')->assertRedirect();
    }

    public function test_guest_is_redirected_to_login_for_account_pages(): void
    {
        $this->get('/vi/tai-khoan')->assertRedirect(localized_route('login', ['locale' => 'vi']));
    }

    public function test_account_page_shows_shared_auth_props(): void
    {
        $user = User::factory()->traveler()->create();

        $this->actingAs($user)->get('/vi/tai-khoan')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/Edit')
                ->where('auth.user.email', $user->email)
                ->where('auth.user.role', 'traveler')
                ->where('auth.user.province', null)
                ->where('auth.user.unread_notifications', 0)
                ->where('user.email', $user->email)
                ->where('preferences.channels_level4', ['inapp', 'email', 'sms']));
    }

    public function test_agency_shared_props_include_province(): void
    {
        $agency = User::factory()->agency('KH')->create();

        $this->actingAs($agency)->get('/vi/tai-khoan')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.role', 'agency')
                ->where('auth.user.province.code', 'KH'));
    }

    public function test_unread_notifications_count_only_inapp_unread(): void
    {
        $user = User::factory()->traveler()->create();
        \App\Models\Notification::factory()->for($user)->count(2)->create();
        \App\Models\Notification::factory()->for($user)->create(['read_at' => now(), 'status' => 'read']);
        \App\Models\Notification::factory()->for($user)->channel('email')->create();

        $this->actingAs($user)->get('/vi')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.unread_notifications', 2));
    }

    public function test_profile_and_preferences_update(): void
    {
        $user = User::factory()->traveler()->create();

        $this->actingAs($user)->put('/vi/tai-khoan', [
            'name' => 'Tên Mới',
            'nationality' => 'vn',
            'emergency_contact_name' => 'Người thân',
            'emergency_contact_phone' => '0912345678',
            'preferences' => ['channels_level1' => ['email'], 'quiet_from' => '22:00', 'quiet_to' => '06:00', 'digest_daily' => false],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $user->refresh();
        $this->assertSame('Tên Mới', $user->name);
        $this->assertSame('VN', $user->nationality);
        $this->assertSame(['email'], $user->notificationPreference->channels_level1);
        $this->assertFalse($user->notificationPreference->digest_daily);
        // Mức 4 luôn gửi mọi kênh dù cấu hình khác
        $this->assertSame(['inapp', 'email', 'sms'], $user->notificationPreference->channelsFor(4));
    }

    public function test_profile_update_rejects_invalid_values(): void
    {
        $user = User::factory()->traveler()->create();

        $this->actingAs($user)->put('/vi/tai-khoan', [
            'name' => 'A',
            'phone' => 'abc',
            'nationality' => 'Viet Nam',
            'preferences' => ['channels_level2' => ['fax'], 'quiet_from' => '25:00'],
        ])->assertSessionHasErrors(['name', 'phone', 'nationality', 'preferences.channels_level2.0', 'preferences.quiet_from']);
    }

    public function test_theme_endpoint_updates_theme_only(): void
    {
        $user = User::factory()->traveler()->create(['theme' => 'light']);

        $this->actingAs($user)->patch('/vi/tai-khoan/giao-dien', ['theme' => 'dark'])->assertRedirect();
        $this->assertSame('dark', $user->fresh()->theme);
        $this->actingAs($user)->patch('/vi/tai-khoan/giao-dien', ['theme' => 'neon'])->assertSessionHasErrors('theme');
    }
}
