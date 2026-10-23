<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\Channels\SmsSimulatorChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/** BUG-6: nhật ký SMS mô phỏng che số điện thoại, không ghi nội dung tin, có notification_id/user_id. */
class SmsLogPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_log_masks_phone_and_omits_message_text(): void
    {
        config(['dukhach.notifications.sms_fail_rate' => 0]);
        $user = User::factory()->traveler()->create(['phone' => '0994617421']);
        $notification = Notification::factory()->channel('sms')->create([
            'user_id' => $user->id, 'title' => 'Cảnh báo bão', 'body' => 'Nội dung bí mật cho đoàn',
        ]);
        $logged = null;
        $channel = Mockery::mock(LoggerInterface::class);
        $channel->shouldReceive('info')->once()->withArgs(function (string $message, array $context) use (&$logged) {
            $logged = $context;

            return true;
        });
        Log::shouldReceive('channel')->with('nghiepvu')->andReturn($channel);

        $result = app(SmsSimulatorChannel::class)->send($notification);

        $this->assertTrue($result->ok);
        $this->assertSame('099*****21', $logged['to']);
        $this->assertSame($notification->id, $logged['notification_id']);
        $this->assertSame($user->id, $logged['user_id']);
        $this->assertArrayNotHasKey('text', $logged);
        $encoded = json_encode($logged, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('0994617421', $encoded);
        $this->assertStringNotContainsString('bí mật', $encoded);
    }

    public function test_mask_phone_handles_short_and_international_numbers(): void
    {
        $this->assertSame('+84*******21', SmsSimulatorChannel::maskPhone('+84994617421'));
        $this->assertSame('****', SmsSimulatorChannel::maskPhone('1234'));
    }
}
