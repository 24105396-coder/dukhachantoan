<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Lỗi của /api/* luôn là JSON {message} với thông điệp chung theo mã trạng thái:
 * không lộ tên lớp model ("No query results for model [App\Models\...]"), đường dẫn hay stack trace.
 * ValidationException (422) giữ định dạng mặc định {message, errors} để client biết trường sai.
 */
final class ApiErrorResponse
{
    public const MESSAGES = [
        400 => 'Bad request.',
        401 => 'Unauthenticated.',
        403 => 'Forbidden.',
        404 => 'Not found.',
        405 => 'Method not allowed.',
        419 => 'Page expired.',
        429 => 'Too many requests.',
        500 => 'Server error.',
        503 => 'Service unavailable.',
    ];

    public static function applies(Request $request): bool
    {
        return $request->is('api', 'api/*');
    }

    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::applies($request) || $e instanceof ValidationException) {
            return null;
        }

        [$status, $headers] = self::statusOf($e);
        // Môi trường debug (không phải test) giữ chi tiết lỗi 5xx cho lập trình viên
        if ($status >= 500 && config('app.debug') && ! app()->runningUnitTests()) {
            return null;
        }

        $message = self::MESSAGES[$status] ?? ($status >= 500 ? self::MESSAGES[500] : 'Error.');

        return response()->json(['message' => $message], $status, $headers);
    }

    /** @return array{0:int,1:array<string,string>} */
    private static function statusOf(Throwable $e): array
    {
        return match (true) {
            $e instanceof ModelNotFoundException => [404, []],
            $e instanceof AuthenticationException => [401, []],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getHeaders()],
            default => [500, []],
        };
    }
}
