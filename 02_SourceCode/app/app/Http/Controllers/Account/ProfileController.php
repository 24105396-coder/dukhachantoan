<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly SessionRevoker $revoker)
    {
    }

    public function edit(Request $request): Response
    {
        $user = $request->user()->load('role');

        return Inertia::render('Account/Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'organization' => $user->organization,
                'locale' => $user->locale,
                'theme' => $user->theme,
                'role' => $user->role?->code,
                'role_label' => $user->role ? __('enums.roles.'.$user->role->code) : null,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $previousLocale = $user->locale;
        $user->fill($data)->save();
        $this->audit->log('account.profile_updated', $user, ['fields' => array_keys($data)], $user);

        // Chỉ đổi theme (nút trên thanh đầu trang): quay lại trang cũ, không flash
        if ($this->isThemeOnly($data, $user)) {
            return back();
        }

        // Đổi ngôn ngữ thì chuyển hướng sang URL theo ngôn ngữ mới
        if (isset($data['locale']) && $data['locale'] !== $previousLocale) {
            return redirect(localized_route('account.profile', ['locale' => $user->locale]))
                ->with('success', __('messages.profile.updated', [], $user->locale));
        }

        return back()->with('success', __('messages.profile.updated'));
    }

    /** Body chỉ chứa giá trị cũ ngoài theme (useTheme gửi kèm name/phone/locale hiện tại). */
    private function isThemeOnly(array $data, User $user): bool
    {
        $others = array_values(array_diff(array_keys($data), ['theme']));

        return isset($data['theme']) && ($others === [] || ! $user->wasChanged($others));
    }

    public function password(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $password = $request->validated('password');
        $user->forceFill(['password' => $password])->save();
        // Hủy phiên ở thiết bị khác: đổi hash trong phiên (auth.session), xóa dòng sessions khác, đổi remember_token
        Auth::logoutOtherDevices($password);
        $this->revoker->revokeOthers($user, $request->session()->getId());
        $request->session()->regenerate(true); // bỏ luôn bản phiên cũ
        $this->audit->log('account.password_changed', $user, [], $user);

        return back()->with('success', __('messages.profile.password_updated'));
    }
}
