<?php

namespace App\Http\Middleware;

use App\Services\AccessDeniedLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kiểm tra vai trò ở máy chủ: `role:admin,manager`. Khách hoặc vai trò không khớp → 403,
 * kèm nhật ký cảnh báo + audit_logs `access.denied` để phát hiện dò quyền.
 */
class EnsureRole
{
    public function __construct(private readonly AccessDeniedLogger $denied) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $this->denied->record($request, 'inactive', ['required' => $roles]);
            Auth::guard('web')->logout();
            abort(403, __('messages.auth.inactive'));
        }

        if (! $user || ! $user->hasRole(...$roles)) {
            $this->denied->record($request, 'role', ['required' => $roles]);
            abort(403);
        }

        return $next($request);
    }
}
