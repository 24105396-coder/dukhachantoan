<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\AlertService;
use App\Services\NotificationService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Hàng đợi thông báo có thử lại (bắt buộc theo đề): backoff, failed sau 5 lần, nhắc lại mức 4, không gửi trùng. */
class NotificationQueueTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    private function smsFor(?User $user = null, int $level = 3): Notification
    {
        $user ??= User::factory()->traveler()->create(['phone' => '0912345678']);

        return Notification::factory()->channel('sms')->create(['user_id' => $user->id, 'level' => $level]);
    }

    private function failRate(float $rate): void
    {
        config(['dukhach.notifications.sms_fail_rate' => $rate]);
    }

    public function test_first_failure_retries_after_one_minute_then_succeeds(): void
    {
        Carbon::setTestNow('2026-10-22 10:00:00');
        $notification = $this->smsFor();
        $this->failRate(1.0);

        $stats = app(NotificationService::class)->dispatchDue();
        $notification->refresh();
        $this->assertSame(1, $stats['retry']);
        $this->assertSame(Notification::STATUS_QUEUED, $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertSame('2026-10-22 10:01:00', $notification->next_attempt_at->toDateTimeString());
        $this->assertNotNull($notification->last_error);

        // Chưa tới hạn → không gửi
        $this->failRate(0.0);
        $this->assertSame(0, app(NotificationService::class)->dispatchDue()['claimed']);

        Carbon::setTestNow('2026-10-22 10:01:00');
        $this->assertSame(1, app(NotificationService::class)->dispatchDue()['sent']);
        $notification->refresh();
        $this->assertSame(Notification::STATUS_SENT, $notification->status);
        $this->assertSame(2, $notification->attempts);
        $this->assertNotNull($notification->sent_at);
        $this->assertSame(['failed', 'sent'], $notification->deliveries()->pluck('status')->all());
        $this->assertSame([1, 2], $notification->deliveries()->pluck('attempt_no')->all());
        Carbon::setTestNow();
    }

    public function test_five_failures_mark_notification_failed_and_log_warning(): void
    {
        $channel = Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with('nghiepvu')->andReturn($channel);
        $this->failRate(1.0);
        $notification = $this->smsFor();
        $expectedWaits = [1, 5, 15, 60];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            app(NotificationService::class)->dispatchDue();
            $notification->refresh();
            if ($attempt < 5) {
                $this->assertSame(Notification::STATUS_QUEUED, $notification->status);
                $this->assertEqualsWithDelta($expectedWaits[$attempt - 1] * 60, now()->diffInSeconds($notification->next_attempt_at), 2);
                $this->travel($expectedWaits[$attempt - 1])->minutes();
            }
        }

        $this->assertSame(Notification::STATUS_FAILED, $notification->status);
        $this->assertSame(5, $notification->attempts);
        $this->assertSame(5, NotificationDelivery::where('notification_id', $notification->id)->where('status', 'failed')->count());
        $channel->shouldHaveReceived('warning')->once();
        $this->assertSame(0, app(NotificationService::class)->dispatchDue()['claimed']);
    }

    public function test_unread_emergency_is_reminded_once_after_thirty_minutes(): void
    {
        $user = User::factory()->traveler()->create(['phone' => '0912345678']);
        $this->failRate(0.0);
        Notification::factory()->create(['user_id' => $user->id, 'level' => 4, 'channel' => 'inapp', 'title' => 'Bão khẩn cấp']);
        app(NotificationService::class)->dispatchDue();

        $this->travel(29)->minutes();
        $this->assertSame(0, app(NotificationDispatcher::class)->remindUnreadEmergencies());

        $this->travel(2)->minutes();
        $this->assertSame(1, app(NotificationService::class)->dispatchDue()['reminders']);
        $reminders = Notification::query()->whereNotNull('payload->reminder_of')->get();
        $this->assertEqualsCanonicalizing(['inapp', 'email', 'sms'], $reminders->pluck('channel')->all());
        $this->assertStringStartsWith('[Nhắc lại]', $reminders->first()->title);

        // Chỉ nhắc một lần, kể cả khi nhắc lại cũng chưa đọc
        $this->travel(40)->minutes();
        app(NotificationService::class)->dispatchDue();
        $this->assertSame(0, app(NotificationDispatcher::class)->remindUnreadEmergencies());
        $this->assertSame(3, Notification::query()->whereNotNull('payload->reminder_of')->count());
    }

    public function test_read_emergency_is_not_reminded(): void
    {
        $notification = Notification::factory()->create(['level' => 4, 'channel' => 'inapp']);
        app(NotificationService::class)->dispatchDue();
        app(NotificationService::class)->markRead($notification->fresh());

        $this->travel(45)->minutes();
        $this->assertSame(0, app(NotificationDispatcher::class)->remindUnreadEmergencies());
    }

    public function test_two_workers_never_claim_the_same_notification(): void
    {
        Notification::factory()->count(5)->create();
        $dispatcher = app(NotificationDispatcher::class);

        $first = $dispatcher->claimDue(3);
        $second = $dispatcher->claimDue(10);

        $this->assertCount(3, $first);
        $this->assertCount(2, $second);
        $this->assertEmpty(array_intersect($first->pluck('id')->all(), $second->pluck('id')->all()));
        $this->assertSame(5, Notification::where('status', Notification::STATUS_SENDING)->count());
        $this->assertCount(0, $dispatcher->claimDue(10));
    }

    public function test_stale_sending_is_requeued(): void
    {
        $n = Notification::factory()->create(['status' => Notification::STATUS_SENDING]);
        Notification::query()->whereKey($n->id)->update(['updated_at' => now()->subMinutes(20)]);

        $stats = app(NotificationService::class)->dispatchDue();

        $this->assertSame(1, $stats['recovered']);
        $this->assertSame(Notification::STATUS_SENT, $n->fresh()->status);
    }

    public function test_quiet_hours_defer_low_levels_but_not_warnings(): void
    {
        Carbon::setTestNow('2026-10-22 23:30:00');
        $user = User::factory()->traveler()->create(['phone' => '0912345678']);
        NotificationPreference::create(array_merge(NotificationPreference::defaults(), [
            'user_id' => $user->id, 'channels_level2' => ['inapp', 'email'], 'quiet_from' => '22:00', 'quiet_to' => '06:30',
        ]));
        $service = app(NotificationService::class);

        $low = $service->queueFor($user->fresh(), 2, ['title' => 'Lưu ý', 'body' => 'x'])->keyBy('channel');
        $high = $service->queueFor($user->fresh(), 3, ['title' => 'Cảnh báo', 'body' => 'y'])->keyBy('channel');

        $this->assertNull($low['inapp']->next_attempt_at);
        $this->assertSame('2026-10-23 06:30:00', $low['email']->next_attempt_at->toDateTimeString());
        $this->assertNull($high['email']->next_attempt_at);
        Carbon::setTestNow();
    }

    public function test_level_one_email_is_merged_into_daily_digest(): void
    {
        Carbon::setTestNow('2026-10-22 15:00:00');
        $user = User::factory()->traveler()->create();
        NotificationPreference::create(array_merge(NotificationPreference::defaults(), ['user_id' => $user->id, 'channels_level1' => ['inapp', 'email'], 'digest_daily' => true]));
        $service = app(NotificationService::class);

        $service->queueFor($user->fresh(), 1, ['title' => 'Tin 1', 'body' => 'a']);
        $service->queueFor($user->fresh(), 1, ['title' => 'Tin 2', 'body' => 'b']);

        $digest = Notification::where('channel', 'email')->sole();
        $this->assertSame('2026-10-23 07:00:00', $digest->next_attempt_at->toDateTimeString());
        $this->assertCount(2, $digest->payload['items']);
        $this->assertStringContainsString('Tin 2', $digest->body);
        $this->assertSame(2, Notification::where('channel', 'inapp')->count());
        Carbon::setTestNow();
    }

    public function test_enqueue_respects_preferences_and_is_idempotent(): void
    {
        $zone = $this->zoneIn();
        $partner = $this->partner();
        $trip = $this->tripInZone($zone);
        $trip->forceFill(['partner_id' => $partner->id])->save();
        NotificationPreference::create(array_merge(NotificationPreference::defaults(), ['user_id' => $trip->user_id, 'channels_level2' => ['inapp', 'sms']]));
        $alert = $this->alertForZones([$zone], 2, Alert::STATUS_DRAFT);

        $result = app(AlertService::class)->publish($alert, null);
        app(AlertService::class)->propagate($alert->fresh());
        $this->artisan('app:match-alerts')->assertSuccessful();

        $this->assertSame(['matches' => 1, 'notifications' => 3], $result);
        $this->assertEqualsCanonicalizing(['inapp', 'sms'], Notification::where('user_id', $trip->user_id)->pluck('channel')->all());
        // Đơn vị lữ hành không có số điện thoại → chỉ in-app
        $this->assertSame(['inapp'], Notification::where('user_id', $partner->id)->pluck('channel')->all());
        $this->assertSame(3, Notification::count());
    }

    public function test_dispatch_command_sends_and_records_deliveries(): void
    {
        $this->failRate(0.0);
        Notification::factory()->count(2)->create();
        Notification::factory()->channel('email')->create();

        $this->artisan('app:dispatch-notifications')->assertSuccessful();

        $this->assertSame(3, Notification::where('status', 'sent')->count());
        $this->assertSame(3, NotificationDelivery::where('status', 'sent')->count());
        $this->getJson('/healthz')->assertOk()->assertJsonPath('queue.queued', 0);
    }
}
