<?php

namespace App\Http\Resources;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use Illuminate\Http\Request;

/**
 * NotificationItem; deliveries chỉ có khi quan hệ đã nạp (trang hàng đợi quản trị).
 *
 * @mixin Notification
 */
class NotificationItem extends BaseResource
{
    public function toArray(Request $request): array
    {
        $n = $this->resource;

        return [
            'id' => $n->id,
            'channel' => $n->channel,
            'level' => (int) $n->level,
            'title' => $n->title,
            'body' => $n->body,
            'action_url' => $n->action_url,
            'status' => $n->status,
            'attempts' => (int) $n->attempts,
            'max_attempts' => (int) $n->max_attempts,
            'next_attempt_at' => self::iso($n->next_attempt_at),
            'sent_at' => self::iso($n->sent_at),
            'read_at' => self::iso($n->read_at),
            'last_error' => $n->last_error,
            'created_at' => self::iso($n->created_at),
            'is_reminder' => (bool) ($n->payload['reminder_of'] ?? false),
            'is_digest' => (bool) ($n->payload['digest'] ?? false),
            'recipient' => $n->relationLoaded('user') && $n->user ? ['name' => $n->user->name] : null,
            'user' => $n->relationLoaded('user') && $n->user ? ['name' => $n->user->name, 'email' => $n->user->email] : null,
            'alert' => $n->alert ? ['code' => $n->alert->code, 'severity' => (int) $n->alert->severity, 'headline' => $n->alert->headline] : null,
            'trip' => $n->trip ? ['code' => $n->trip->code, 'title' => $n->trip->title] : null,
            'deliveries' => $n->relationLoaded('deliveries') ? $n->deliveries->map(fn (NotificationDelivery $d) => [
                'attempt_no' => (int) $d->attempt_no,
                'channel' => $d->channel,
                'status' => $d->status,
                'response_code' => $d->response_code,
                'attempted_at' => self::iso($d->attempted_at),
                'duration_ms' => $d->duration_ms,
            ])->values()->all() : [],
        ];
    }
}
