<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Trang tài khoản (account.edit): hồ sơ, tùy chọn kênh thông báo, đổi mật khẩu, theme. */
class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly SessionRevoker $revoker)
    {
    }

    public function edit(Request $request): Response
    {
        $user = $request->user()->load('role', 'province', 'notificationPreference');

        return Inertia::render('Account/Edit', [
            'user' => $this->profile($user),
            'preferences' => $this->preferences($user),
            'channels' => Notification::CHANNELS,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->profileData();
        $previousLocale = $user->locale;
        $user->fill($data)->save();

        $preferences = $request->preferenceData();
        if ($preferences !== null) {
            $current = $user->notificationPreference?->only(array_keys(NotificationPreference::defaults()))
                ?? NotificationPreference::defaults();
            $user->notificationPreference()->updateOrCreate(['user_id' => $user->id], array_merge($current, $preferences));
        }
        $this->audit->log('account.profile_updated', $user, ['fields' => array_merge(array_keys($data), $preferences !== null ? ['preferences'] : [])], $user);

        // Đổi ngôn ngữ thì chuyển hướng sang URL theo ngôn ngữ mới
        if (isset($data['locale']) && $data['locale'] !== $previousLocale) {
            return redirect(localized_route('account.edit', ['locale' => $user->locale]))
                ->with('success', __('messages.profile.updated', [], $user->locale));
        }

        return back()->with('success', __('messages.profile.updated'));
    }

    /** Nút đổi theme trên thanh đầu trang (useTheme): không flash, quay lại trang cũ. */
    public function theme(Request $request): RedirectResponse
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['light', 'dark'])]]);
        $request->user()->forceFill($data)->save();

        return back();
    }

    public function password(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $password = $request->validated('password');
        $user->forceFill(['password' => $password])->save();
        // Hủy phiên ở thiết bị khác: đổi hash trong phiên (auth.session), xóa dòng sessions khác, đổi remember_token
        Auth::logoutOtherDevices($password);
        $this->revoker->revokeOthers($user, $request->session()->getId());
        $request->session()->regenerate(true);
        $this->audit->log('account.password_changed', $user, [], $user);

        return back()->with('success', __('messages.profile.password_updated'));
    }

    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'nationality' => $user->nationality,
            'organization' => $user->organization,
            'emergency_contact_name' => $user->emergency_contact_name,
            'emergency_contact_phone' => $user->emergency_contact_phone,
            'locale' => $user->locale,
            'theme' => $user->theme,
            'role' => $user->role?->code,
            'role_label' => $user->role ? __('enums.roles.'.$user->role->code) : null,
            'province' => $user->province?->brief(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    private function preferences(User $user): array
    {
        $pref = $user->notificationPreference;
        $defaults = NotificationPreference::defaults();
        if (! $pref) {
            return $defaults;
        }

        return [
            'channels_level1' => $pref->channels_level1 ?? $defaults['channels_level1'],
            'channels_level2' => $pref->channels_level2 ?? $defaults['channels_level2'],
            'channels_level3' => $pref->channels_level3 ?? $defaults['channels_level3'],
            'channels_level4' => $pref->channels_level4 ?? $defaults['channels_level4'],
            'quiet_from' => $pref->quiet_from ? substr($pref->quiet_from, 0, 5) : null,
            'quiet_to' => $pref->quiet_to ? substr($pref->quiet_to, 0, 5) : null,
            'digest_daily' => (bool) $pref->digest_daily,
            'language' => $pref->language ?? $user->locale,
        ];
    }
}
