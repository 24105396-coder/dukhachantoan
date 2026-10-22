<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $roleId = Role::query()->where('code', Role::MEMBER)->value('id')
            ?? Role::create(['code' => Role::MEMBER, 'name' => 'Thành viên'])->id;

        // Vai trò luôn là member; không nhận role từ đầu vào người dùng
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role_id' => $roleId,
            'locale' => app()->getLocale(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $audit->log('auth.register', $user, [], $user);

        return redirect(localized_route('account.dashboard'))->with('success', __('messages.auth.registered'));
    }
}
