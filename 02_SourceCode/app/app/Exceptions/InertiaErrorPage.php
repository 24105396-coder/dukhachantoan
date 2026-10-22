<?php

namespace App\Exceptions;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Request Inertia (header X-Inertia) gặp lỗi HTTP thì trả trang Vue "Error" để giữ layout,
 * request thường vẫn dùng resources/views/errors/{code}.blade.php mặc định của Laravel.
 */
final class InertiaErrorPage
{
    public const STATUSES = [403, 404, 419, 429, 500, 503];

    public static function respond(Response $response, Throwable $e, Request $request): Response
    {
        $status = $response->getStatusCode();
        if (! $request->header('X-Inertia') || ! in_array($status, self::STATUSES, true)) {
            return $response;
        }
        // Môi trường debug giữ trang lỗi chi tiết của Laravel cho lỗi máy chủ
        if ($status >= 500 && config('app.debug') && ! app()->runningUnitTests()) {
            return $response;
        }

        try {
            self::ensureLocale($request);
            $middleware = app(HandleInertiaRequests::class);
            Inertia::version(fn () => $middleware->version($request));
            Inertia::share(self::sharedProps($middleware, $request));

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        } catch (Throwable $inner) {
            // Không để lỗi khi dựng trang lỗi che mất phản hồi gốc
            report($inner);

            return $response;
        }
    }

    /** Lỗi xảy ra trước khi SetLocale chạy (vd 404 không khớp route): lấy locale từ đoạn đầu URL. */
    private static function ensureLocale(Request $request): void
    {
        $segment = $request->segment(1);
        if (in_array($segment, SetLocale::SUPPORTED, true) && app()->getLocale() !== $segment) {
            app()->setLocale($segment);
            URL::defaults(['locale' => $segment]);
        }
    }

    private static function sharedProps(HandleInertiaRequests $middleware, Request $request): array
    {
        $props = $middleware->share($request);
        if (! $request->hasSession()) {
            // Chưa qua StartSession: không đọc flash/người dùng từ session
            $props['flash'] = ['success' => null, 'error' => null];
            $props['auth'] = ['user' => null];
        }

        return $props;
    }
}
