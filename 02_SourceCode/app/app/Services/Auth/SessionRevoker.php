<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Thu hồi phiên đăng nhập của một người dùng: xóa dòng trong bảng `sessions`
 * (driver database) và đổi remember_token để cookie "ghi nhớ" cũ hết hiệu lực.
 */
class SessionRevoker
{
    /** Xóa mọi phiên của người dùng (khi bị khóa tài khoản). */
    public function revokeAll(User $user): int
    {
        $this->rotateRememberToken($user);

        return $this->sessions($user)->delete();
    }

    /** Xóa các phiên khác, giữ phiên hiện tại (khi tự đổi mật khẩu). */
    public function revokeOthers(User $user, ?string $currentSessionId): int
    {
        $this->rotateRememberToken($user);

        return $this->sessions($user)->when($currentSessionId, fn ($q, $id) => $q->where('id', '!=', $id))->delete();
    }

    private function rotateRememberToken(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    private function sessions(User $user)
    {
        $table = (string) config('session.table', 'sessions');

        return DB::connection(config('session.connection'))->table($table)->where('user_id', $user->id);
    }
}
