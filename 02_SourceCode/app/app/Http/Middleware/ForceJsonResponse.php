<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ép Accept: application/json cho /api/*: client không gửi header (curl, trình duyệt) vẫn nhận lỗi dạng JSON
 * thay vì trang HTML, và Laravel render ValidationException thành 422 JSON.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
