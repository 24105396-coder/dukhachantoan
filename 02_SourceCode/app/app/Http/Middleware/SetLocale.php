<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đặt ngôn ngữ từ tiền tố URL /{locale}; lưu vào session và hồ sơ người dùng.
 * Route không có tiền tố (vd. /healthz, POST ngoài nhóm) dùng locale trong session.
 */
class SetLocale
{
    public const SUPPORTED = ['vi', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $fromRoute = $route?->parameter('locale');
        $locale = $fromRoute ?? ($request->hasSession() ? $request->session()->get('locale') : null) ?? config('app.locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale', 'vi');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        if ($fromRoute !== null) {
            // Controller không cần nhận tham số locale
            $route->forgetParameter('locale');
            if ($request->hasSession()) {
                $request->session()->put('locale', $locale);
            }
            $this->rememberForUser($request, $locale);
        }

        return $next($request);
    }

    private function rememberForUser(Request $request, string $locale): void
    {
        $user = $request->user();
        if ($user && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }
    }
}
