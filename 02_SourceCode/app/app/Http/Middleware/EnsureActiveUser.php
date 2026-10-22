<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tài khoản bị khóa (is_active=false) mà vẫn còn phiên/cookie ghi nhớ → đăng xuất,
 * hủy phiên, quay về trang đăng nhập kèm thông báo. Chạy trên mọi route nhóm web.
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user || $user->is_active) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $locale = in_array($request->segment(1), SetLocale::SUPPORTED, true) ? $request->segment(1) : app()->getLocale();
        $message = __('messages.auth.inactive', [], $locale);
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        return redirect(localized_route('login', ['locale' => $locale]))->with('error', $message);
    }
}
