<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\SimulationRun;
use App\Models\SimulationScenario;
use App\Models\Trip;
use Database\Factories\FactoryHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Phân quyền nghiệp vụ ở máy chủ: 6 ca truy cập trái phép theo dka-conventions. */
class RbacBusinessTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    public function test_traveler_gets_403_on_admin_area_and_admin_alerts(): void
    {
        $traveler = $this->traveler();

        $this->actingAs($traveler)->get('/vi/quan-tri')->assertForbidden();
        $this->actingAs($traveler)->get('/vi/quan-tri/canh-bao')->assertForbidden();
        $this->actingAs($traveler)->post('/vi/quan-tri/canh-bao/tao', [])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'access.denied', 'user_id' => $traveler->id]);
    }

    public function test_partner_cannot_edit_trip_of_another_owner(): void
    {
        $trip = $this->tripInZone($this->zoneIn('KH'));
        $partner = $this->partner();

        $this->actingAs($partner)->get("/vi/hanh-trinh/{$trip->code}")->assertForbidden();
        $this->actingAs($partner)->get("/vi/hanh-trinh/{$trip->code}/sua")->assertForbidden();
        $this->actingAs($partner)->put("/vi/hanh-trinh/{$trip->code}/sua", ['title' => 'Chiếm quyền'])->assertForbidden();
        $this->actingAs($partner)->post("/vi/hanh-trinh/{$trip->code}/huy")->assertForbidden();
        $this->assertSame(Trip::STATUS_ACTIVE, $trip->fresh()->status);
        $this->assertNotSame('Chiếm quyền', $trip->fresh()->title);
    }

    public function test_agency_of_province_a_cannot_view_trip_in_province_b(): void
    {
        $tripHanoi = $this->tripInZone($this->zoneIn('HN'));
        $tripKhanhHoa = $this->tripInZone($this->zoneIn('KH'));
        $agencyKh = $this->agency('KH');

        $this->actingAs($agencyKh)->get("/vi/quan-tri/hanh-trinh/{$tripHanoi->code}")->assertForbidden();
        $this->actingAs($agencyKh)->get("/vi/quan-tri/hanh-trinh/{$tripKhanhHoa->code}")->assertOk();
        // Danh sách cũng bị giới hạn theo tỉnh
        $this->actingAs($agencyKh)->get('/vi/quan-tri/hanh-trinh')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Trips/Index')->has('trips.data', 1)
                ->where('trips.data.0.code', $tripKhanhHoa->code));
    }

    public function test_agency_cannot_run_or_finish_simulation(): void
    {
        $scenario = SimulationScenario::create([
            'slug' => 'kich-ban-thu', 'name' => 'Kịch bản thử', 'hazard_type_id' => FactoryHelpers::hazardTypeId(),
            'severity' => 3, 'zone_codes' => [], 'duration_hours' => 6, 'payload' => ['steps' => [['at_minute' => 0, 'headline' => 'X']]],
        ]);
        $run = SimulationRun::create(['scenario_id' => $scenario->id, 'started_at' => now()]);
        $agency = $this->agency(null);

        $this->actingAs($agency)->get('/vi/quan-tri/mo-phong')->assertForbidden();
        $this->actingAs($agency)->post('/vi/quan-tri/mo-phong/chay', ['slug' => $scenario->slug])->assertForbidden();
        $this->actingAs($agency)->post("/vi/quan-tri/mo-phong/lan-chay/{$run->id}/ket-thuc")->assertForbidden();
        $this->assertSame(1, SimulationRun::count());
    }

    public function test_guest_creating_trip_is_redirected_to_login(): void
    {
        $this->get('/vi/hanh-trinh/tao')->assertRedirect('/vi/dang-nhap');
        $this->post('/vi/hanh-trinh/tao', ['title' => 'X'])->assertRedirect('/vi/dang-nhap');
        $this->get('/vi/thong-bao')->assertRedirect('/vi/dang-nhap');
        $this->assertSame(0, Trip::count());
    }

    public function test_traveler_cannot_read_notification_of_another_user(): void
    {
        $owner = $this->traveler();
        $other = $this->traveler();
        $notification = Notification::factory()->create(['user_id' => $owner->id, 'status' => Notification::STATUS_SENT]);

        $this->actingAs($other)->post("/vi/thong-bao/{$notification->id}/da-doc")->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
        // Danh sách chỉ có thông báo của chính mình
        $this->actingAs($other)->get('/vi/thong-bao')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Notifications/Index')->has('notifications.data', 0));
        $this->actingAs($owner)->post("/vi/thong-bao/{$notification->id}/da-doc")->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
