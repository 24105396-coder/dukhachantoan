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
        $user = User::factory()->member()->create(['email' => 'user@disanso.test']);
        $this->get('/vi/dang-nhap');
        $oldSession = session()->getId();

        $response = $this->post('/vi/dang-nhap', ['email' => 'USER@disanso.test', 'password' => 'password']);

        $response->assertRedirect(localized_route('account.dashboard', ['locale' => 'vi']));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_returns_error_on_email_field(): void
    {
        User::factory()->create(['email' => 'user@disanso.test']);

        $this->from('/vi/dang-nhap')->post('/vi/dang-nhap', ['email' => 'user@disanso.test', 'password' => 'sai-mat-khau'])
            ->assertRedirect('/vi/dang-nhap')
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', 'auth.login_failed')->count());
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'locked@disanso.test']);

        $this->post('/vi/dang-nhap', ['email' => 'locked@disanso.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'user@disanso.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/vi/dang-nhap', ['email' => 'user@disanso.test', 'password' => 'wrong'.$i]);
        }

        $this->post('/vi/dang-nhap', ['email' => 'user@disanso.test', 'password' => 'password'])
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

    public function test_register_creates_member_and_ignores_role_input(): void
    {
        $this->post('/vi/dang-ky', [
            'name' => 'Nguyễn Văn A',
            'email' => 'moi@disanso.test',
            'password' => 'MatKhau123',
            'password_confirmation' => 'MatKhau123',
            'role_id' => 1,
            'role' => 'admin',
        ])->assertRedirect(localized_route('account.dashboard', ['locale' => 'vi']));

        $user = User::where('email', 'moi@disanso.test')->firstOrFail();
        $this->assertSame('member', $user->role->code);
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

    public function test_dashboard_shows_shared_auth_props(): void
    {
        $user = User::factory()->member()->create();

        $this->actingAs($user)->get('/vi/tai-khoan')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/Dashboard')
                ->where('auth.user.email', $user->email)
                ->where('auth.user.role', 'member')
                ->has('bookings')->has('contributions')->has('favorites')->has('ownRoutes'));
    }
}
