<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertMatch;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Trip;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationScheduler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Thông báo phân cấp 4 mức (F4): xếp hàng theo tùy chọn kênh của người nhận, gộp bản tin mức 1,
 * giờ yên lặng cho mức ≤ 2; gửi/thử lại/nhắc lại do NotificationDispatcher đảm nhiệm.
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationScheduler $scheduler,
        private readonly NotificationDispatcher $dispatcher,
        private readonly AuditLogger $audit,
    ) {
    }

    /** Xếp thông báo cho nhiều đối sánh; trả tổng số bản ghi notifications tạo/gộp. */
    public function enqueueMatches(iterable $matches): int
    {
        return collect($matches)->sum(fn (AlertMatch $m) => $this->enqueue($m));
    }

    /**
     * Xếp thông báo cho chủ hành trình và đơn vị lữ hành của một đối sánh "new".
     * Khóa dòng đối sánh để hai tiến trình không xếp trùng; xong đổi trạng thái → notified.
     */
    public function enqueue(AlertMatch $match): int
    {
        return DB::transaction(function () use ($match) {
            $locked = AlertMatch::query()->whereKey($match->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== AlertMatch::STATUS_NEW) {
                return 0;
            }
            $locked->load(['alert.translations', 'alert.source', 'trip.user.notificationPreference', 'trip.partner.notificationPreference',
                'leg.zone', 'leg.destination.translations']);
            $alert = $locked->alert;
            $trip = $locked->trip;
            if (! $alert || ! $trip || ! $alert->isLive() || $trip->status !== Trip::STATUS_ACTIVE) {
                return 0;
            }
            $created = 0;
            foreach ($this->tripRecipients($trip) as $user) {
                if ($this->alreadyNotified($user, $trip, $alert)) {
                    continue;
                }
                $locale = $this->localeFor($user, $trip);
                $created += $this->queueFor($user, (int) $alert->severity, $this->matchMessage($locked, $locale), [
                    'trip_id' => $trip->id, 'alert_id' => $alert->id, 'match_id' => $locked->id,
                ])->count();
            }
            $locked->forceFill(['status' => AlertMatch::STATUS_NOTIFIED])->save();
            $trip->forceFill(['last_alert_at' => now()])->saveQuietly();

            return $created;
        });
    }

    /**
     * Tạo thông báo cho một người nhận trên các kênh theo tùy chọn (hoặc $channels chỉ định).
     * $message = {title, body, action_url, locale?, payload?}; $links = {trip_id, alert_id, match_id, checkin_id}.
     *
     * @return Collection<int, Notification>
     */
    public function queueFor(User $user, int $level, array $message, array $links = [], ?array $channels = null): Collection
    {
        $level = max(1, min(4, $level));
        $pref = $this->preferenceFor($user);
        $channels = $level >= 4 ? NotificationPreference::DEFAULT_CHANNELS[4] : ($channels ?: $pref->channelsFor($level));
        $locale = $message['locale'] ?? $this->localeFor($user);
        $out = collect();
        foreach (array_unique(array_intersect($channels, Notification::CHANNELS)) as $channel) {
            if (! $this->reachable($user, $channel)) {
                continue;
            }
            $sendAt = $this->scheduler->sendAt($pref, $level, $channel);
            $notification = $this->scheduler->isDigest($pref, $level, $channel)
                ? $this->scheduler->appendToDigest($user, $channel, $sendAt, $message, $links, $locale)
                : Notification::create(array_merge($links, [
                    'user_id' => $user->id,
                    'channel' => $channel,
                    'level' => $level,
                    'locale' => $locale,
                    'title' => Str::limit($message['title'], 250),
                    'body' => $message['body'] ?? null,
                    'action_url' => $message['action_url'] ?? null,
                    'status' => Notification::STATUS_QUEUED,
                    'next_attempt_at' => $sendAt,
                    'payload' => $message['payload'] ?? null,
                ]));
            $out->push($notification);
        }

        return $out;
    }

    /** Gửi các thông báo đến hạn (xem NotificationDispatcher). */
    public function dispatchDue(?int $limit = null): array
    {
        return $this->dispatcher->dispatchDue($limit);
    }

    public function markRead(Notification $notification): void
    {
        if ($notification->read_at !== null) {
            return;
        }
        $notification->forceFill(['read_at' => now(), 'status' => Notification::STATUS_READ])->save();
    }

    /** Đánh dấu mọi thông báo trong ứng dụng của người dùng là đã đọc; trả số bản ghi. */
    public function markAllRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now(), 'status' => Notification::STATUS_READ, 'updated_at' => now()]);
    }

    /** Đưa thông báo thất bại/đã hủy về hàng đợi để gửi lại ngay (đếm lần thử lại từ đầu). */
    public function resend(Notification $notification, User $actor): Notification
    {
        $notification->forceFill([
            'status' => Notification::STATUS_QUEUED,
            'attempts' => 0,
            'next_attempt_at' => null,
        ])->save();
        $this->audit->log('notification.resend', $notification, ['channel' => $notification->channel], $actor);

        return $notification;
    }

    /**
     * Phát thông báo thủ công tới du khách/đơn vị lữ hành có hành trình đang diễn ra trong các vùng.
     *
     * @return array{recipients: int, notifications: int}
     */
    public function broadcast(array $zoneIds, int $level, string $title, string $body, array $channels, User $actor): array
    {
        $trips = Trip::query()->ongoing()->with('user.notificationPreference', 'partner.notificationPreference')
            ->whereHas('legs', fn ($q) => $q->whereIn('zone_id', $zoneIds)
                ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now())))
            ->get();
        $sent = [];
        $count = 0;
        foreach ($trips as $trip) {
            foreach ($this->tripRecipients($trip) as $user) {
                if (isset($sent[$user->id])) {
                    continue;
                }
                $sent[$user->id] = true;
                $count += $this->queueFor($user, $level, [
                    'title' => $title,
                    'body' => $body,
                    'action_url' => localized_route('trips.show', ['locale' => $this->localeFor($user, $trip), 'trip' => $trip->code]),
                    'payload' => ['broadcast_by' => $actor->id],
                ], ['trip_id' => $trip->id], $channels)->count();
            }
        }
        $this->audit->log('notification.broadcast', null, [
            'zone_ids' => $zoneIds, 'level' => $level, 'channels' => $channels, 'title' => $title,
            'recipients' => count($sent), 'notifications' => $count,
        ], $actor);

        return ['recipients' => count($sent), 'notifications' => $count];
    }

    /** Hủy các thông báo còn xếp hàng của cảnh báo (khi cảnh báo hết hạn/bị hủy). */
    public function cancelQueuedForAlert(Alert $alert): int
    {
        return Notification::query()->where('alert_id', $alert->id)->where('status', Notification::STATUS_QUEUED)
            ->update(['status' => Notification::STATUS_CANCELLED, 'updated_at' => now()]);
    }

    /** Hủy các thông báo còn xếp hàng của hành trình (khi hủy hành trình). */
    public function cancelQueuedForTrip(Trip $trip): int
    {
        return Notification::query()->where('trip_id', $trip->id)->where('status', Notification::STATUS_QUEUED)
            ->update(['status' => Notification::STATUS_CANCELLED, 'updated_at' => now()]);
    }

    /** Chủ hành trình + đơn vị lữ hành (nếu khác). @return Collection<int, User> */
    public function tripRecipients(Trip $trip): Collection
    {
        return collect([$trip->user, $trip->partner])->filter(fn ($u) => $u instanceof User && $u->is_active)->unique('id')->values();
    }

    public function preferenceFor(User $user): NotificationPreference
    {
        return $user->notificationPreference ?? new NotificationPreference(NotificationPreference::defaults() + ['language' => $user->locale ?? 'vi']);
    }

    public function localeFor(User $user, ?Trip $trip = null): string
    {
        $locale = $user->notificationPreference?->language ?: $user->locale ?: $trip?->preferred_locale ?: 'vi';

        return in_array($locale, ['vi', 'en'], true) ? $locale : 'vi';
    }

    /** Kênh có địa chỉ nhận: email cần email, SMS cần số điện thoại. */
    private function reachable(User $user, string $channel): bool
    {
        return match ($channel) {
            Notification::CHANNEL_EMAIL => (bool) $user->email,
            Notification::CHANNEL_SMS => (bool) $user->phone,
            default => true,
        };
    }

    private function alreadyNotified(User $user, Trip $trip, Alert $alert): bool
    {
        return Notification::query()->where('user_id', $user->id)->where('trip_id', $trip->id)
            ->where('alert_id', $alert->id)->whereNull('payload->reminder_of')->exists();
    }

    /** Tiêu đề "[Mức] tiêu đề cảnh báo", nội dung gồm hành trình, chặng, mô tả và nguồn theo ngôn ngữ người nhận. */
    private function matchMessage(AlertMatch $match, string $locale): array
    {
        $alert = $match->alert;
        $t = $alert->translate($locale);
        $leg = $match->leg;
        $place = $leg?->title ?: ($leg?->destination?->translated('name', $locale) ?? $leg?->zone?->localizedName($locale) ?? '');
        $severity = __('enums.severity.'.(Alert::SEVERITIES[(int) $alert->severity] ?? 'info'), [], $locale);

        return [
            'locale' => $locale,
            'title' => '['.$severity.'] '.($t?->headline ?? $alert->code),
            'body' => __('messages.notify.match_body', [
                'trip' => $match->trip->title,
                'place' => $place,
                'description' => Str::limit(strip_tags((string) $t?->description), 300),
                'instructions' => Str::limit(strip_tags((string) $t?->instructions), 200),
                'source' => $alert->source_name,
            ], $locale),
            'action_url' => localized_route('trips.show', ['locale' => $locale, 'trip' => $match->trip->code]),
            'payload' => ['alert_code' => $alert->code, 'headline' => $t?->headline, 'match_id' => $match->id],
        ];
    }
}
