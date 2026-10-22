<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ghi nhận truy cập trái phép (403) ở hai nơi: log ứng dụng (cảnh báo, theo ngày) và audit_logs
 * (`access.denied`) để quản trị xem trên giao diện nhật ký. Không ghi query string vì có thể chứa chữ ký/token.
 */
class AccessDeniedLogger
{
    public const ACTION = 'access.denied';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  string  $reason  role | inactive | policy | signature
     */
    public function record(Request $request, string $reason, array $extra = []): void
    {
        $user = $request->user();
        $context = [
            'user_id' => $user?->id,
            'role' => $user?->role?->code ?? 'guest',
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'path' => Str::limit('/'.ltrim($request->path(), '/'), 200, ''),
            'ip' => $request->ip(),
            'reason' => $reason,
        ] + $extra;

        Log::warning('Truy cập trái phép', $context);
        $this->audit->log(self::ACTION, null, $context, $user);
    }
}
