<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** H1 (giới hạn đăng nhập không vượt được bằng X-Forwarded-For/đổi IP), M1 (khóa tài khoản), M2 (đổi mật khẩu). */
class AuthSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $email, string $ip, ?string $xff = null)
    {
        $headers = $xff ? ['X-Forwarded-For' => $xff] : [];

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/vi/dang-nhap', ['email' => $email, 'password' => 'wrong-password'], $headers);
    }

    public function test_spoofed_x_forwarded_for_does_not_bypass_login_limiter(): void
    {
        User::factory()->create(['email' => 'victim@disanso.test']);

        for ($i = 1; $i <= 5; $i++) {
            $this->attempt('victim@disanso.test', '127.0.0.1', "10.9.9.$i")->assertStatus(422);
        }
        $this->attempt('victim@disanso.test', '127.0.0.1', '10.9.9.99')->assertStatus(429);
    }

    public function test_x_forwarded_for_is_not_trusted_for_client_ip(): void
    {
        User::factory()->create(['email' => 'victim@disanso.test']);

        $this->attempt('victim@disanso.test', '192.0.2.10', '10.77.0.1')->assertStatus(422);

        $log = AuditLog::where('action', 'auth.login_failed')->latest('id')->firstOrFail();
        $this->assertSame('192.0.2.10', $log->ip);
    }

    public function test_email_limiter_blocks_distributed_attempts_from_many_ips(): void
    {
        User::factory()->create(['email' => 'victim@disanso.test']);

        for ($i = 1; $i <= 5; $i++) {
            $this->attempt('Victim@Disanso.test', "198.51.100.$i")->assertStatus(422);
        }
        // IP mới hoàn toàn, email đổi hoa/thường: vẫn bị chặn theo email đã chuẩn hóa
        $this->attempt('VICTIM@disanso.test', '203.0.113.200')->assertStatus(429);
        $this->attempt('other@disanso.test', '203.0.113.200')->assertStatus(422);
    }

    public function test_deactivated_user_with_open_session_is_logged_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->get('/vi/tai-khoan')
            ->assertRedirect(localized_route('login', ['locale' => 'vi']))
            ->assertSessionHas('error', __('messages.auth.inactive'));
        $this->assertGuest();
    }

    public function test_admin_locking_user_deletes_their_sessions_and_rotates_remember_token(): void
    {
        $target = $this->member();
        $target->forceFill(['remember_token' => 'old-token'])->save();
        $this->seedSessions($target, ['dev-a', 'dev-b']);
        $other = $this->member();
        $this->seedSessions($other, ['other-dev']);

        $this->actingAs($this->admin())->patch("/vi/quan-tri/nguoi-dung/{$target->id}", [
            'role_id' => Role::where('code', 'member')->value('id'), 'is_active' => false,
        ])->assertSessionHas('success');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertNotSame('old-token', $target->fresh()->remember_token);
        $this->assertFalse((bool) $target->fresh()->is_active);
    }

    public function test_password_change_revokes_other_sessions_and_remember_token(): void
    {
        $user = User::factory()->create(['password' => 'OldPass123']);
        $user->forceFill(['remember_token' => 'old-token'])->save();
        $this->seedSessions($user, ['laptop', 'phone']);

        $this->actingAs($user)->patch('/vi/tai-khoan/mat-khau', [
            'current_password' => 'OldPass123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewPass456', $fresh->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertNotSame('old-token', $fresh->remember_token);
        // auth.session: phiên hiện tại mang hash mật khẩu mới nên vẫn đăng nhập
        $this->assertTrue(session()->has('password_hash_web'));
        $this->get('/vi/tai-khoan')->assertOk();
        $this->assertAuthenticatedAs($fresh);
    }

    public function test_session_with_stale_password_hash_is_logged_out(): void
    {
        $user = User::factory()->create();

        // Phiên ở thiết bị khác còn hash mật khẩu cũ (mật khẩu đã đổi ở nơi khác)
        $this->actingAs($user)->withSession(['password_hash_web' => Hash::make('stale')])
            ->get('/vi/tai-khoan')->assertRedirect(localized_route('login', ['locale' => 'vi']));
        $this->assertGuest();
    }

    private function seedSessions(User $user, array $ids): void
    {
        foreach ($ids as $id) {
            DB::table('sessions')->insert([
                'id' => $id.'-'.$user->id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
                'user_agent' => 'test', 'payload' => base64_encode('x'), 'last_activity' => time(),
            ]);
        }
    }
}
