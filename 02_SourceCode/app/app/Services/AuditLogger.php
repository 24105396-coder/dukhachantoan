<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/** Ghi nhật ký kiểm toán; lỗi ghi log không được làm hỏng nghiệp vụ chính. */
class AuditLogger
{
    public function log(string $action, ?Model $subject = null, array $meta = [], ?User $user = null): ?AuditLog
    {
        try {
            $request = request();

            return AuditLog::create([
                'user_id' => $user?->id ?? Auth::id(),
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'ip' => $request?->ip(),
                'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
