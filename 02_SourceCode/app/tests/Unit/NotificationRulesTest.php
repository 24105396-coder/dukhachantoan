<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\TripMember;
use PHPUnit\Framework\TestCase;

class NotificationRulesTest extends TestCase
{
    public function test_backoff_schedule_matches_conventions(): void
    {
        $this->assertSame([1, 5, 15, 60, 240], Notification::BACKOFF_MINUTES);
        $this->assertSame(1, Notification::backoffMinutes(1));
        $this->assertSame(15, Notification::backoffMinutes(3));
        $this->assertSame(240, Notification::backoffMinutes(5));
        $this->assertSame(240, Notification::backoffMinutes(9));
        $this->assertSame(1, Notification::backoffMinutes(0));
    }

    public function test_level_four_always_uses_every_channel(): void
    {
        $pref = new NotificationPreference(['channels_level4' => ['inapp'], 'channels_level1' => ['email', 'fax']]);

        $this->assertSame(['inapp', 'email', 'sms'], $pref->channelsFor(4));
        $this->assertSame(['email'], $pref->channelsFor(1));
        $this->assertSame(['inapp', 'email'], $pref->channelsFor(3));
    }

    public function test_phone_masking_hides_four_middle_digits(): void
    {
        $this->assertSame('091****678', TripMember::maskPhone('0912345678'));
        $this->assertSame('+44 7****000000', TripMember::maskPhone('+44 7000 000000'));
        $this->assertSame('*****', TripMember::maskPhone('12345'));
        $this->assertNull(TripMember::maskPhone(null));
    }
}
