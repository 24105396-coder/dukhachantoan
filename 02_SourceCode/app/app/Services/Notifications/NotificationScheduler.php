<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Quy tắc thời điểm gửi: mức ≥ 3 gửi ngay; mức ≤ 2 qua email/SMS tôn trọng giờ yên lặng;
 * mức 1 qua email/SMS khi bật bản tin ngày được gộp vào một thông báo gửi lúc digest_hour.
 * Kênh in-app luôn hiển thị ngay (không làm phiền).
 */
class NotificationScheduler
{
    /** Mức tối đa áp dụng giờ yên lặng. */
    public const QUIET_MAX_LEVEL = 2;

    public function isDigest(NotificationPreference $pref, int $level, string $channel): bool
    {
        return $level === 1 && $channel !== Notification::CHANNEL_INAPP && (bool) ($pref->digest_daily ?? false);
    }

    /** Thời điểm gửi sớm nhất; null = ngay. */
    public function sendAt(NotificationPreference $pref, int $level, string $channel, ?Carbon $now = null): ?Carbon
    {
        $now ??= now();
        if ($level > self::QUIET_MAX_LEVEL || $channel === Notification::CHANNEL_INAPP) {
            return null;
        }
        if ($this->isDigest($pref, $level, $channel)) {
            return $this->nextDigestAt($now);
        }

        return $this->quietEnd($pref, $now);
    }

    /** Lần gửi bản tin kế tiếp (hôm nay nếu chưa tới giờ, không thì ngày mai). */
    public function nextDigestAt(Carbon $now): Carbon
    {
        $at = $now->copy()->setTime((int) config('dukhach.notifications.digest_hour', 7), 0);

        return $at->lte($now) ? $at->addDay() : $at;
    }

    /** Nếu đang trong giờ yên lặng thì trả thời điểm kết thúc; khung có thể vắt qua nửa đêm. */
    public function quietEnd(NotificationPreference $pref, Carbon $now): ?Carbon
    {
        if (! $pref->quiet_from || ! $pref->quiet_to) {
            return null;
        }
        $from = $now->copy()->setTimeFromTimeString((string) $pref->quiet_from);
        $to = $now->copy()->setTimeFromTimeString((string) $pref->quiet_to);
        if ($from->eq($to)) {
            return null;
        }
        if ($from->lt($to)) {
            return $now->gte($from) && $now->lt($to) ? $to : null;
        }
        if ($now->gte($from)) {
            return $to->addDay();
        }

        return $now->lt($to) ? $to : null;
    }

    /** Gộp vào bản tin ngày đang xếp hàng của người dùng trên kênh (tạo mới nếu chưa có). */
    public function appendToDigest(User $user, string $channel, Carbon $sendAt, array $message, array $links, string $locale): Notification
    {
        $key = $sendAt->format('Y-m-d H:i');
        $digest = Notification::query()->where('user_id', $user->id)->where('channel', $channel)
            ->where('status', Notification::STATUS_QUEUED)->where('payload->digest_key', $key)->lockForUpdate()->first();
        $item = ['title' => $message['title'], 'alert_id' => $links['alert_id'] ?? null, 'match_id' => $links['match_id'] ?? null,
            'trip_id' => $links['trip_id'] ?? null, 'action_url' => $message['action_url'] ?? null];
        $items = array_merge($digest?->payload['items'] ?? [], [$item]);
        $attributes = [
            'title' => __('messages.notify.digest_title', ['count' => count($items), 'date' => $sendAt->format('d/m')], $locale),
            'body' => collect($items)->map(fn ($i) => '• '.$i['title'])->implode("\n"),
            'action_url' => localized_route('notifications.index', ['locale' => $locale]),
            'payload' => ['digest' => true, 'digest_key' => $key, 'items' => $items],
        ];
        if ($digest) {
            $digest->forceFill($attributes)->save();

            return $digest;
        }

        return Notification::create($attributes + [
            'user_id' => $user->id,
            'trip_id' => $links['trip_id'] ?? null,
            'alert_id' => $links['alert_id'] ?? null,
            'channel' => $channel,
            'level' => 1,
            'locale' => $locale,
            'status' => Notification::STATUS_QUEUED,
            'next_attempt_at' => $sendAt,
        ]);
    }
}
