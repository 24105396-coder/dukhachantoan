<?php

use App\Exceptions\ApiErrorResponse;
use App\Exceptions\InertiaErrorPage;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Services\AccessDeniedLogger;
use App\Services\Payment\PaymentException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
        then: function () {
            // Health check tự viết: không qua session/CSRF để Docker gọi nhanh
            Route::get('/healthz', HealthController::class)->name('healthz');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Mặc định không tin proxy nào (client không giả được IP qua X-Forwarded-For).
        // Triển khai sau reverse proxy/PaaS: đặt TRUSTED_PROXIES="10.0.0.0/8,..." hoặc "*".
        $trusted = trim((string) env('TRUSTED_PROXIES', ''));
        if ($trusted !== '') {
            $middleware->trustProxies(at: $trusted === '*' ? '*' : array_map('trim', explode(',', $trusted)));
        }

        $middleware->alias([
            'role' => EnsureRole::class,
            'locale' => SetLocale::class,
        ]);

        $middleware->web(append: [
            // auth.session: đổi mật khẩu → phiên ở thiết bị khác (giữ hash cũ) bị đăng xuất
            AuthenticateSession::class,
            EnsureActiveUser::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);

        // ForceJsonResponse đứng đầu để cả lỗi 429 của throttle cũng là JSON
        $middleware->api(prepend: [ForceJsonResponse::class, 'throttle:api']);

        // auth có thể chạy trước SetLocale (thứ tự ưu tiên middleware) → lấy locale từ đoạn đầu URL
        $middleware->redirectGuestsTo(fn (Request $request) => localized_route('login', [
            'locale' => in_array($request->segment(1), SetLocale::SUPPORTED, true) ? $request->segment(1) : app()->getLocale(),
        ]));
        $middleware->redirectUsersTo(fn (Request $request) => localized_route('account.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Chữ ký callback thanh toán sai → 400, không tiết lộ chi tiết
        $exceptions->render(function (PaymentException $e, Request $request) {
            if ($e->getCode() === 400) {
                return $request->expectsJson()
                    ? response()->json(['message' => $e->getMessage()], 400)
                    : response($e->getMessage(), 400);
            }

            return null;
        });

        // Quá số lần đăng nhập: báo lỗi ngay trên ô email thay vì trang 429 trống
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->routeIs('login.store') && ! $request->expectsJson()) {
                $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);

                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => __('auth.throttle', ['seconds' => $seconds])]);
            }

            return null;
        });

        // Policy/FormRequest từ chối (AuthorizationException → AccessDeniedHttpException) hoặc chữ ký URL sai/hết hạn:
        // ghi cảnh báo + audit access.denied, trả null để các bước dựng phản hồi bên dưới vẫn chạy
        $exceptions->render(function (AccessDeniedHttpException|InvalidSignatureException $e, Request $request) {
            $reason = $e instanceof InvalidSignatureException ? 'signature' : 'policy';
            if ($reason === 'signature' || $e->getPrevious() instanceof AuthorizationException) {
                app(AccessDeniedLogger::class)->record($request, $reason);
            }

            return null;
        });

        // /api/*: luôn JSON {message} chung (404/403/405/429/500), không lộ tên lớp model
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => ApiErrorResponse::applies($request) || $request->expectsJson());
        $exceptions->render(fn (Throwable $e, Request $request) => ApiErrorResponse::render($e, $request));

        // Request Inertia → trang Vue Error (status); request thường → Blade errors/*
        $exceptions->respond(fn ($response, Throwable $e, Request $request) => InertiaErrorPage::respond($response, $e, $request));
    })->create();
