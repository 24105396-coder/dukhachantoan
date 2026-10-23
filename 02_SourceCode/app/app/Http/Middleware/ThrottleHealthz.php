<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Giới hạn tần suất cho /healthz (công khai, mỗi lần gọi chạy vài truy vấn CSDL).
 * Khác throttle mặc định: nếu kho đếm (CACHE_STORE=database) không truy cập được thì bỏ qua giới hạn,
 * để /healthz vẫn trả 503 JSON "db: fail" thay vì 500 khi CSDL sập.
 */
class ThrottleHealthz extends ThrottleRequests
{
    protected function handleRequest($request, Closure $next, array $limits)
    {
        try {
            foreach ($limits as $limit) {
                if ($this->limiter->tooManyAttempts($limit->key, $limit->maxAttempts)) {
                    throw $this->buildException($request, $limit->key, $limit->maxAttempts, $limit->responseCallback);
                }
                $this->limiter->hit($limit->key, $limit->decaySeconds);
            }
        } catch (ThrottleRequestsException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('healthz throttle bypassed: '.class_basename($e));

            return $next($request);
        }

        $response = $next($request);
        foreach ($limits as $limit) {
            $response = $this->addHeaders($response, $limit->maxAttempts, $this->remaining($limit));
        }

        return $response;
    }

    /** Số lượt còn lại; kho đếm lỗi giữa chừng thì coi như còn đủ (không làm hỏng phản hồi). */
    private function remaining(object $limit): int
    {
        try {
            return $this->calculateRemainingAttempts($limit->key, $limit->maxAttempts);
        } catch (Throwable) {
            return (int) $limit->maxAttempts;
        }
    }
}
