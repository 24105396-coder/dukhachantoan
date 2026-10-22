<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quên/đặt lại mật khẩu qua email (MAIL_MAILER=log). Luôn trả cùng thông báo dù email có tồn tại hay không
 * để không lộ danh sách tài khoản.
 */
class PasswordResetController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', ['status' => $request->session()->get('status')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);
        $status = Password::sendResetLink(['email' => mb_strtolower(trim($data['email']))]);
        Log::info('Yêu cầu đặt lại mật khẩu', ['status' => $status, 'ip' => $request->ip()]);

        return back()->with('status', __('passwords.sent'));
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request, AuditLogger $audit, SessionRevoker $revoker): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);
        $data['email'] = mb_strtolower(trim($data['email']));

        $status = Password::reset($data, function (User $user, string $password) use ($audit, $revoker) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $revoker->revokeAll($user);
            $audit->log('auth.password_reset', $user, [], $user);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect(localized_route('login'))->with('status', __($status));
    }
}
