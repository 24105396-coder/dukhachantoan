<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', ['status' => $request->session()->get('status')]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => mb_strtolower(trim($request->validated('email'))),
            'password' => $request->validated('password'),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->audit->log('auth.login_failed', null, ['email' => $credentials['email']]);
            // Tệp log không chứa email thô: chỉ dấu băm ngắn để đối chiếu với audit_logs khi cần
            Log::warning('Đăng nhập thất bại', ['email_hash' => substr(hash('sha256', $credentials['email']), 0, 12), 'ip' => $request->ip()]);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // Chống session fixation: cấp session id mới sau khi đăng nhập
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->log('auth.login', $user, [], $user);
        Log::info('Đăng nhập thành công', ['user_id' => $user->id, 'role' => $user->role?->code, 'ip' => $request->ip()]);

        return redirect()->intended(localized_route('account.dashboard', ['locale' => $user->locale ?: app()->getLocale()]))
            ->with('success', __('messages.auth.logged_in', ['name' => $user->name]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->audit->log('auth.logout', $user, [], $user);
        Log::info('Đăng xuất', ['user_id' => $user?->id, 'ip' => $request->ip()]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(localized_route('home'))->with('success', __('messages.auth.logged_out'));
    }
}
