<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nhật ký từng lần thử gửi một thông báo. */
class NotificationDelivery extends Model
{
    protected $fillable = [
        'notification_id', 'attempt_no', 'channel', 'status', 'response_code', 'response_body', 'duration_ms', 'attempted_at',
    ];

    protected function casts(): array
    {
        return ['attempt_no' => 'integer', 'response_code' => 'integer', 'duration_ms' => 'integer', 'attempted_at' => 'datetime'];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
