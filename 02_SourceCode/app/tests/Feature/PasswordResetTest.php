<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_renders(): void
    {
        $this->get('/vi/quen-mat-khau')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword')->where('status', null));
    }

    public function test_reset_link_is_sent_with_localized_url_and_same_message_for_unknown_email(): void
    {
        Notification::fake();
        $user = User::factory()->traveler()->create(['email' => 'a@dukhach.test', 'locale' => 'en']);

        $this->from('/vi/quen-mat-khau')->post('/vi/quen-mat-khau', ['email' => 'A@dukhach.test'])
            ->assertRedirect('/vi/quen-mat-khau')->assertSessionHas('status');
        $known = session('status');
        $this->from('/vi/quen-mat-khau')->post('/vi/quen-mat-khau', ['email' => 'khong-co@dukhach.test'])
            ->assertSessionHas('status', $known);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_contains($url, '/en/dat-lai-mat-khau/'.$n->token) && str_contains($url, 'email=a%40dukhach.test');
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->traveler()->create(['email' => 'b@dukhach.test']);
        $token = Password::createToken($user);

        $this->get("/vi/dat-lai-mat-khau/{$token}?email=b@dukhach.test")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword')->where('token', $token)->where('email', 'b@dukhach.test'));

        $this->post('/vi/dat-lai-mat-khau', [
            'token' => $token, 'email' => 'b@dukhach.test', 'password' => 'MoiMatKhau9', 'password_confirmation' => 'MoiMatKhau9',
        ])->assertRedirect(localized_route('login', ['locale' => 'vi']));

        $this->assertTrue(Hash::check('MoiMatKhau9', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset', 'user_id' => $user->id]);
    }

    public function test_invalid_token_or_weak_password_is_rejected(): void
    {
        $user = User::factory()->traveler()->create(['email' => 'c@dukhach.test']);

        $this->post('/vi/dat-lai-mat-khau', [
            'token' => 'sai', 'email' => 'c@dukhach.test', 'password' => 'MoiMatKhau9', 'password_confirmation' => 'MoiMatKhau9',
        ])->assertSessionHasErrors('email');
        $this->post('/vi/dat-lai-mat-khau', [
            'token' => Password::createToken($user), 'email' => 'c@dukhach.test', 'password' => 'yeu', 'password_confirmation' => 'yeu',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
