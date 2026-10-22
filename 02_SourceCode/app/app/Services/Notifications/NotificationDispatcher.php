<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\Channels\InAppChannel;
use App\Services\Notifications\Channels\NotificationChannel;
use App\Services\Notifications\Channels\SmsSimulatorChannel;
use App\Services\NotificationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gửi thông báo đến hạn: nhận lô bằng transaction + lockForUpdate (đổi sang "sending" trước khi nhả khóa
 * nên hai worker không gửi trùng), gửi qua kênh, ghi notification_deliveries, thất bại → backoff 1/5/15/60/240 phút,
 * quá max_attempts → failed + nhật ký WARNING kênh nghiepvu; mức 4 chưa đọc sau 30 phút → nhắc lại một lần.
 */
class NotificationDispatcher
{
    public const REMINDER_BATCH = 200;

    /** @return array{claimed:int, sent:int, retry:int, failed:int, reminders:int, recovered:int} */
    public function dispatchDue(?int $limit = null): array
    {
        $stats = ['claimed' => 0, 'sent' => 0, 'retry' => 0, 'failed' => 0, 'reminders' => 0, 'recovered' => $this->recoverStale()];
        $limit ??= (int) config('dukhach.notifications.dispatch_batch', 100);
        foreach ($this->claimDue($limit) as $notification) {
            $stats['claimed']++;
            $stats[$this->deliver($notification)]++;
        }
        $stats['reminders'] = $this->remindUnreadEmergencies();

        return $stats;
    }

    /**
     * Nhận một lô thông báo đến hạn: SELECT … FOR UPDATE rồi đổi sang "sending" trong cùng transaction.
     *
     * @return Collection<int, Notification>
     */
    public function claimDue(int $limit): Collection
    {
        $ids = DB::transaction(function () use ($limit) {
            $ids = Notification::query()->due()->orderByDesc('level')->orderBy('id')
                ->limit($limit)->lockForUpdate()->pluck('id');
            if ($ids->isNotEmpty()) {
                Notification::query()->whereIn('id', $ids)->update(['status' => Notification::STATUS_SENDING, 'updated_at' => now()]);
            }

            return $ids;
        });

        return $ids->isEmpty() ? collect() : Notification::query()->with('user')->whereIn('id', $ids)
            ->orderByDesc('level')->orderBy('id')->get();
    }

    /** Gửi một thông báo đã nhận; trả 'sent' | 'retry' | 'failed'. */
    public function deliver(Notification $notification): string
    {
        $started = microtime(true);
        try {
            $result = $this->channel($notification->channel)->send($notification);
        } catch (Throwable $e) {
            $result = ChannelResult::fail(Str::limit($e->getMessage(), 250));
        }
        $attempt = (int) $notification->attempts + 1;
        NotificationDelivery::create([
            'notification_id' => $notification->id,
            'attempt_no' => $notification->deliveries()->count() + 1,
            'channel' => $notification->channel,
            'status' => $result->ok ? 'sent' : 'failed',
            'response_code' => $result->code,
            'response_body' => Str::limit($result->body, 500),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'attempted_at' => now(),
        ]);

        return $result->ok ? $this->markSent($notification, $attempt, $result) : $this->markFailedAttempt($notification, $attempt, $result);
    }

    /** Mức 4 trong ứng dụng đã gửi mà chưa đọc sau 30 phút → tạo nhắc lại (mọi kênh) đúng một lần. */
    public function remindUnreadEmergencies(): int
    {
        $cutoff = now()->subMinutes(Notification::EMERGENCY_REMIND_AFTER_MINUTES);
        $candidates = Notification::query()->where('level', 4)->where('channel', Notification::CHANNEL_INAPP)
            ->where('status', Notification::STATUS_SENT)->whereNull('read_at')->where('sent_at', '<=', $cutoff)
            ->whereNull('payload->reminder_of')->whereNull('payload->reminded_at')
            ->orderBy('id')->limit(self::REMINDER_BATCH)->pluck('id');

        return $candidates->sum(fn (int $id) => $this->remindOne($id));
    }

    private function remindOne(int $id): int
    {
        return DB::transaction(function () use ($id) {
            $original = Notification::query()->with('user.notificationPreference')->whereKey($id)->lockForUpdate()->first();
            if (! $original || $original->read_at !== null || isset($original->payload['reminded_at']) || ! $original->user) {
                return 0;
            }
            $original->forceFill(['payload' => array_merge($original->payload ?? [], ['reminded_at' => now()->toIso8601String()])])->save();
            $created = app(NotificationService::class)->queueFor($original->user, 4, [
                'locale' => $original->locale,
                'title' => __('messages.notify.reminder_prefix', [], $original->locale).' '.$original->title,
                'body' => $original->body,
                'action_url' => $original->action_url,
                'payload' => array_merge(Arr::except($original->payload ?? [], ['reminded_at']), ['reminder_of' => $original->id]),
            ], array_filter([
                'trip_id' => $original->trip_id, 'alert_id' => $original->alert_id,
                'match_id' => $original->match_id, 'checkin_id' => $original->checkin_id,
            ]));

            return $created->count() > 0 ? 1 : 0;
        });
    }

    /** Thông báo kẹt ở "sending" (worker dừng giữa chừng) → đưa lại hàng đợi. */
    private function recoverStale(): int
    {
        $minutes = (int) config('dukhach.notifications.stale_sending_minutes', 10);

        return Notification::query()->where('status', Notification::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->update(['status' => Notification::STATUS_QUEUED, 'updated_at' => now()]);
    }

    private function markSent(Notification $notification, int $attempt, ChannelResult $result): string
    {
        $notification->forceFill([
            'status' => $notification->read_at ? Notification::STATUS_READ : Notification::STATUS_SENT,
            'attempts' => $attempt,
            'sent_at' => now(),
            'next_attempt_at' => null,
            'last_error' => null,
            'provider_ref' => $result->providerRef,
        ])->save();

        return 'sent';
    }

    private function markFailedAttempt(Notification $notification, int $attempt, ChannelResult $result): string
    {
        $exhausted = $attempt >= (int) $notification->max_attempts;
        $notification->forceFill([
            'status' => $exhausted ? Notification::STATUS_FAILED : Notification::STATUS_QUEUED,
            'attempts' => $attempt,
            'next_attempt_at' => $exhausted ? null : now()->addMinutes(Notification::backoffMinutes($attempt)),
            'last_error' => Str::limit($result->body, 500),
        ])->save();
        if ($exhausted) {
            Log::channel('nghiepvu')->warning('Thông báo thất bại sau số lần thử tối đa', [
                'notification_id' => $notification->id, 'channel' => $notification->channel, 'level' => $notification->level,
                'user_id' => $notification->user_id, 'attempts' => $attempt, 'error' => $result->body,
            ]);

            return 'failed';
        }

        return 'retry';
    }

    private function channel(string $channel): NotificationChannel
    {
        return match ($channel) {
            Notification::CHANNEL_EMAIL => app(EmailChannel::class),
            Notification::CHANNEL_SMS => app(SmsSimulatorChannel::class),
            default => app(InAppChannel::class),
        };
    }
}
