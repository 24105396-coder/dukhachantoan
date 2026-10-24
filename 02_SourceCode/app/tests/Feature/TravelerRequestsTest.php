<?php

namespace Tests\Feature;

use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\SafetyCheckin;
use App\Models\User;
use App\Models\Zone;
use Database\Factories\FactoryHelpers;
use Database\Seeders\RequestDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Mục 1.2 góp ý 24/10: du khách theo dõi yêu cầu đã gửi, flash sau khi gửi, thông báo khi cơ quan xử lý. */
class TravelerRequestsTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    private function incident(User $user, Zone $zone, array $attributes = []): IncidentReport
    {
        return IncidentReport::create(array_merge([
            'user_id' => $user->id, 'zone_id' => $zone->id, 'hazard_type_id' => FactoryHelpers::hazardTypeId('sat_lo'),
            'title' => 'Sạt lở chắn đường', 'status' => 'pending',
        ], $attributes));
    }

    public function test_guest_is_sent_to_login_and_api_requires_session(): void
    {
        $this->get('/vi/yeu-cau-cua-toi')->assertRedirect('/vi/dang-nhap');
        $this->getJson('/api/v1/me/requests')->assertUnauthorized();
    }

    public function test_index_lists_only_own_incidents_and_need_help_checkins(): void
    {
        $zone = $this->zoneIn('KH');
        $me = $this->traveler();
        $other = $this->traveler();
        $trip = $this->tripInZone($zone, $me);
        $agency = $this->agency('KH');
        $mine = $this->incident($me, $zone, ['status' => 'verified', 'reviewed_by' => $agency->id, 'review_note' => 'Đã xác minh']);
        $this->incident($me, $zone, ['title' => 'Chờ duyệt']);
        $this->incident($other, $zone, ['title' => 'Của người khác']);
        // Check-in cần hỗ trợ do người thân gửi qua liên kết chia sẻ (user_id null) vẫn thuộc hành trình của tôi
        $shared = SafetyCheckin::factory()->needHelp()->create(['trip_id' => $trip->id, 'user_id' => null, 'source' => 'share_link',
            'response_status' => 'in_progress', 'responded_by' => $agency->id]);
        SafetyCheckin::factory()->create(['trip_id' => $trip->id]); // an toàn → không phải yêu cầu
        SafetyCheckin::factory()->needHelp()->create(['trip_id' => $this->tripInZone($zone, $other)->id]);

        $this->actingAs($me)->get('/vi/yeu-cau-cua-toi?moi=incident:'.$mine->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Requests/Index', false)
                ->has('incidents', 2)
                ->where('incidents.1.id', $mine->id)
                ->where('incidents.1.status', 'verified')
                ->where('incidents.1.reviewer_role', 'agency')
                ->where('incidents.1.review_note', 'Đã xác minh')
                ->where('incidents.1.province.name', 'Khánh Hòa')
                ->missing('incidents.1.reviewer')
                ->has('checkins', 1)
                ->where('checkins.0.id', $shared->id)
                ->where('checkins.0.response_status', 'in_progress')
                ->where('checkins.0.responder_role', 'agency')
                ->where('checkins.0.trip.title', $trip->title)
                ->where('highlight', ['type' => 'incident', 'id' => $mine->id])
                ->where('counts', ['pending' => 1, 'in_progress' => 1, 'done' => 1])
                ->where('auth.user.open_requests', 2));

        $this->actingAs($me)->get('/vi/yeu-cau-cua-toi?moi=incident:abc')
            ->assertInertia(fn (Assert $page) => $page->where('highlight', null));
    }

    public function test_api_returns_same_items_for_logged_in_user(): void
    {
        $zone = $this->zoneIn('KH');
        $me = $this->traveler();
        $this->incident($me, $zone);
        $this->incident($this->traveler(), $zone);

        $this->actingAs($me)->getJson('/api/v1/me/requests?locale=en')->assertOk()
            ->assertJsonCount(1, 'data.incidents')
            ->assertJsonCount(0, 'data.checkins')
            ->assertJsonPath('data.incidents.0.status', 'pending')
            ->assertJsonPath('counts.pending', 1)
            ->assertJsonPath('meta.incidents.total', 1)
            ->assertJsonPath('meta.locale', 'en');
    }

    public function test_incident_store_redirects_to_requests_with_id_in_flash(): void
    {
        $zone = $this->zoneIn('KH');
        $traveler = $this->traveler();

        $response = $this->actingAs($traveler)->post('/vi/bao-su-co', [
            'zone_code' => $zone->code, 'hazard_code' => $this->hazardCode('sat_lo'), 'title' => 'Đá lăn trên đèo',
        ]);

        $incident = IncidentReport::sole();
        $url = localized_route('requests.index', ['locale' => 'vi', 'moi' => 'incident:'.$incident->id]);
        $response->assertRedirect($url)
            ->assertSessionHas('success', "Yêu cầu #{$incident->id} đã gửi thành công, đang chờ cơ quan hỗ trợ tiếp nhận.")
            ->assertSessionHas('success_link', $url);
        $this->actingAs($traveler)->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('highlight', ['type' => 'incident', 'id' => $incident->id])->has('flash.success_link'));
    }

    public function test_need_help_checkin_flash_names_the_province_agency(): void
    {
        $trip = $this->tripInZone($this->zoneIn('KH'));
        $owner = $trip->user;

        $this->actingAs($owner)->from("/vi/hanh-trinh/{$trip->code}")
            ->post("/vi/hanh-trinh/{$trip->code}/bao-an-toan", ['status' => 'need_help', 'message' => 'Kẹt xe trên đèo'])
            ->assertRedirect("/vi/hanh-trinh/{$trip->code}")
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Khánh Hòa') && str_contains($m, 'đang chờ tiếp nhận'))
            ->assertSessionHas('success_link', fn (string $url) => str_contains($url, '/vi/yeu-cau-cua-toi?moi=checkin%3A'));

        $this->actingAs($owner)->post("/vi/hanh-trinh/{$trip->code}/bao-an-toan", ['status' => 'safe'])
            ->assertSessionHas('success', __('messages.checkins.recorded'))->assertSessionMissing('success_link');
    }

    public function test_agency_review_notifies_reporter_once_in_app(): void
    {
        $zone = $this->zoneIn('KH');
        $reporter = $this->traveler();
        $incident = $this->incident($reporter, $zone);
        $agency = $this->agency('KH');
        $payload = ['status' => 'rejected', 'review_note' => 'Không thấy sạt lở tại hiện trường'];

        $this->actingAs($agency)->post("/vi/quan-tri/su-co/{$incident->id}/duyet", $payload)->assertSessionHasNoErrors();
        $this->actingAs($agency)->post("/vi/quan-tri/su-co/{$incident->id}/duyet", $payload)->assertSessionHasNoErrors();

        $notification = Notification::where('user_id', $reporter->id)->sole();
        $this->assertSame([Notification::CHANNEL_INAPP, 2], [$notification->channel, $notification->level]);
        $this->assertStringContainsString("#{$incident->id}", $notification->title);
        $this->assertStringContainsString('Không xác minh được', $notification->title);
        $this->assertStringContainsString('moi=incident%3A'.$incident->id, $notification->action_url);
        $this->assertSame(1, $reporter->unreadNotifications()->count());
    }

    public function test_checkin_response_notifies_owner_without_duplicates(): void
    {
        $trip = $this->tripInZone($this->zoneIn('KH'));
        $checkin = SafetyCheckin::factory()->needHelp()->create(['trip_id' => $trip->id]);
        $agency = $this->agency('KH');
        $payload = ['response_status' => 'in_progress', 'response_note' => 'Đang cử người tới'];

        $this->actingAs($agency)->post("/vi/quan-tri/an-toan/{$checkin->id}/phan-hoi", $payload)->assertSessionHasNoErrors();
        $this->actingAs($agency)->post("/vi/quan-tri/an-toan/{$checkin->id}/phan-hoi", $payload)->assertSessionHasNoErrors();

        $notification = Notification::where('user_id', $trip->user_id)->sole();
        $this->assertSame([Notification::CHANNEL_INAPP, 2], [$notification->channel, $notification->level]);
        $this->assertStringContainsString('moi=checkin%3A'.$checkin->id, $notification->action_url);

        $this->actingAs($agency)->post("/vi/quan-tri/an-toan/{$checkin->id}/phan-hoi", ['response_status' => 'closed', 'response_note' => 'Đã an toàn']);
        $this->assertSame(2, Notification::where('user_id', $trip->user_id)->count());
        $this->actingAs($trip->user)->get('/vi/yeu-cau-cua-toi')
            ->assertInertia(fn (Assert $page) => $page->where('checkins.0.response_status', 'closed')->where('counts.done', 1));
    }

    public function test_demo_seeder_creates_all_request_states_idempotently(): void
    {
        $traveler = User::factory()->traveler()->create(['email' => RequestDemoSeeder::TRAVELER]);
        User::factory()->agency('KH')->create(['email' => RequestDemoSeeder::AGENCY]);
        User::factory()->admin()->create(['email' => RequestDemoSeeder::ADMIN]);
        foreach (['quang-ninh', 'khanh-hoa', 'lao-cai'] as $code) {
            $this->zoneIn('KH', ['code' => $code]);
        }
        foreach (['giao_thong', 'song_lon', 'sat_lo'] as $hazard) {
            FactoryHelpers::hazardTypeId($hazard);
        }
        foreach (['TR-SEED-02', 'TR-SEED-04'] as $code) {
            $this->tripInZone(Zone::first(), $traveler)->forceFill(['code' => $code])->save();
        }

        $this->seed(RequestDemoSeeder::class);
        $this->seed(RequestDemoSeeder::class);

        $this->assertEqualsCanonicalizing(['pending', 'verified', 'rejected'], IncidentReport::pluck('status')->all());
        $this->assertTrue(IncidentReport::all()->every(fn ($i) => $i->is_simulated));
        $this->assertEqualsCanonicalizing(['open', 'in_progress', 'closed'],
            SafetyCheckin::where('status', 'need_help')->pluck('response_status')->all());
        $this->actingAs($traveler)->get('/vi/yeu-cau-cua-toi')->assertInertia(fn (Assert $page) => $page
            ->has('incidents', 3)->has('checkins', 3)->where('counts', ['pending' => 2, 'in_progress' => 1, 'done' => 3]));
    }

    public function test_notification_item_alert_carries_source_and_simulation_flag(): void
    {
        $zone = $this->zoneIn('KH');
        $user = $this->traveler();
        $alert = $this->alertForZones([$zone], 3, \App\Models\Alert::STATUS_ACTIVE, ['source_name' => 'Open-Meteo', 'is_simulated' => false]);
        Notification::factory()->sent()->create(['user_id' => $user->id, 'alert_id' => $alert->id]);

        $this->actingAs($user)->get('/vi/thong-bao')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('notifications.data.0.alert.source.name', 'Open-Meteo')
            ->where('notifications.data.0.alert.is_simulated', false)
            ->has('notifications.data.0.alert.updated_at_source'));
    }
}
