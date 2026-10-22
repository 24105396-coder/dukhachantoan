<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertMatch;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\RiskScore;
use App\Models\SafetyCheckin;
use App\Models\Trip;
use App\Models\TripLeg;
use App\Models\User;
use App\Models\WeatherObservation;
use App\Models\Zone;
use Database\Factories\FactoryHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Quan hệ Eloquent, sinh mã ngẫu nhiên và máy trạng thái cơ bản của các model nghiệp vụ. */
class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_generates_random_code_and_share_token(): void
    {
        $trip = Trip::factory()->create();

        $this->assertMatchesRegularExpression('/^TR-[A-Z0-9]{8}$/', $trip->code);
        $this->assertSame(40, strlen($trip->share_token));
        $this->assertNotSame(Trip::factory()->create()->code, $trip->code);
        $this->assertArrayNotHasKey('share_token', $trip->toArray(), 'share_token không được lộ khi tuần tự hóa');
        $this->assertSame('code', $trip->getRouteKeyName());
    }

    public function test_alert_generates_code_and_follows_transitions(): void
    {
        $alert = Alert::factory()->draft()->create();

        $this->assertMatchesRegularExpression('/^AL-[A-Z0-9]{8}$/', $alert->code);
        $this->assertTrue($alert->canTransitionTo('active'));
        $this->assertFalse($alert->canTransitionTo('expired'));
        $alert->status = 'active';
        $this->assertTrue($alert->canTransitionTo('updated'));
        $this->assertTrue($alert->canTransitionTo('cancelled'));
        $alert->status = 'expired';
        $this->assertSame([], Alert::TRANSITIONS['expired']);
        $this->assertFalse($alert->canTransitionTo('active'));
    }

    public function test_live_scope_excludes_draft_and_ended_alerts(): void
    {
        $live = Alert::factory()->create();
        Alert::factory()->draft()->create();
        Alert::factory()->expiredAt(now()->subMinute())->create();
        Alert::factory()->create(['status' => 'cancelled']);

        $this->assertSame([$live->id], Alert::query()->live()->pluck('id')->all());
    }

    public function test_full_relation_graph(): void
    {
        $partner = User::factory()->partner()->create();
        $zone = Zone::factory()->create();
        $trip = Trip::factory()->forPartner($partner)->create();
        $leg = TripLeg::factory()->for($trip)->for($zone)->create();
        $alert = Alert::factory()->severity(4)->create();
        $alert->zones()->attach($zone);
        $match = AlertMatch::create([
            'alert_id' => $alert->id, 'trip_id' => $trip->id, 'leg_id' => $leg->id, 'match_type' => 'zone',
            'overlap_from' => $leg->starts_at, 'overlap_to' => $leg->ends_at,
        ]);
        $checkin = SafetyCheckin::factory()->for($trip)->needHelp()->create(['alert_id' => $alert->id]);
        $notification = Notification::factory()->for($trip->user)->create([
            'trip_id' => $trip->id, 'alert_id' => $alert->id, 'match_id' => $match->id, 'checkin_id' => $checkin->id, 'level' => 4,
        ]);
        NotificationDelivery::create([
            'notification_id' => $notification->id, 'attempt_no' => 1, 'channel' => 'inapp', 'status' => 'sent', 'attempted_at' => now(),
        ]);
        WeatherObservation::create(['zone_id' => $zone->id, 'observed_at' => now(), 'temp_c' => 31.5, 'fetched_at' => now()]);
        RiskScore::create(['zone_id' => $zone->id, 'computed_at' => now(), 'score' => 72, 'level' => 3, 'factors' => ['weather' => 40]]);
        IncidentReport::create([
            'user_id' => $trip->user_id, 'trip_id' => $trip->id, 'zone_id' => $zone->id,
            'hazard_type_id' => FactoryHelpers::hazardTypeId('sat_lo'), 'title' => 'Sạt lở nhỏ',
        ]);

        $this->assertTrue($trip->partner->is($partner));
        $this->assertTrue($partner->partnerTrips->first()->is($trip));
        $this->assertSame($zone->id, $trip->legs->first()->zone->id);
        $this->assertTrue($alert->zones->first()->is($zone));
        $this->assertTrue($zone->alerts->first()->is($alert));
        $this->assertTrue($leg->matches->first()->is($match));
        $this->assertTrue($match->leg->is($leg));
        $this->assertTrue($notification->match->is($match));
        $this->assertTrue($notification->checkin->is($checkin));
        $this->assertCount(1, $notification->deliveries);
        $this->assertSame(1, $trip->user->unreadNotifications()->count());
        $this->assertSame(72, $zone->latestRisk->score);
        $this->assertSame(31.5, $zone->latestObservation->temp_c);
        $this->assertSame('need_help', $trip->checkins->first()->status);
        $this->assertCount(1, $trip->incidentReports);
        $this->assertSame(['weather' => 40], $zone->latestRisk->factors);
    }

    public function test_leg_position_falls_back_to_zone_centroid(): void
    {
        $zone = Zone::factory()->create(['centroid_lat' => 12.25, 'centroid_lng' => 109.19]);
        $leg = TripLeg::factory()->for($zone)->create(['lat' => null, 'lng' => null]);

        $this->assertSame([12.25, 109.19], $leg->fresh()->position());
    }

    public function test_leg_overlapping_scope(): void
    {
        $inside = TripLeg::factory()->between(now()->addHours(2), now()->addHours(10))->create();
        TripLeg::factory()->between(now()->addDays(5), now()->addDays(6))->create();

        $ids = TripLeg::query()->overlapping(now(), now()->addDay())->pluck('id')->all();

        $this->assertSame([$inside->id], $ids);
    }

    public function test_morph_alias_is_stored_for_audit_subjects(): void
    {
        $trip = Trip::factory()->create();

        app(\App\Services\AuditLogger::class)->log('trip.test', $trip);

        $this->assertDatabaseHas('audit_logs', ['action' => 'trip.test', 'subject_type' => 'trip', 'subject_id' => $trip->id]);
    }
}
