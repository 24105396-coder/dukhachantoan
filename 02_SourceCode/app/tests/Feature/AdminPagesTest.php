<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\EmergencyContact;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\ResponseGuide;
use Database\Factories\FactoryHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Trang quản trị: danh sách, sự cố → cảnh báo, gửi lại, phát thủ công, danh bạ/hướng dẫn theo phạm vi. */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    public function test_admin_pages_render_expected_components(): void
    {
        $admin = $this->admin();
        $zone = $this->zoneIn('KH');
        $this->alertForZones([$zone], 3);
        $pages = [
            '/vi/quan-tri/canh-bao' => 'Admin/Alerts/Index', '/vi/quan-tri/canh-bao/tao' => 'Admin/Alerts/Form',
            '/vi/quan-tri/hanh-trinh' => 'Admin/Trips/Index', '/vi/quan-tri/an-toan' => 'Admin/Checkins/Index',
            '/vi/quan-tri/thong-bao' => 'Admin/Notifications/Index', '/vi/quan-tri/danh-ba' => 'Admin/Contacts/Index',
            '/vi/quan-tri/danh-ba/tao' => 'Admin/Contacts/Form', '/vi/quan-tri/huong-dan' => 'Admin/Guides/Index',
            '/vi/quan-tri/huong-dan/tao' => 'Admin/Guides/Form', '/vi/quan-tri/vung' => 'Admin/Zones/Index',
            "/vi/quan-tri/vung/{$zone->code}/sua" => 'Admin/Zones/Form', '/vi/quan-tri/nguon' => 'Admin/Sources/Index',
            '/vi/quan-tri/su-co' => 'Admin/Incidents/Index', '/vi/quan-tri/mo-phong' => 'Admin/Scenarios/Index',
        ];
        foreach ($pages as $url => $component) {
            $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component, false));
        }
        $alert = Alert::sole();
        $this->actingAs($admin)->get("/vi/quan-tri/canh-bao/{$alert->code}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Alerts/Show', false)->where('alert.affected_trips_count', 0)->has('matches')->has('notifications.by_status')
            ->where('transitions', ['updated', 'expired', 'cancelled']));
        $this->actingAs($this->agency('KH'))->get('/vi/quan-tri/nguon')->assertForbidden();
    }

    public function test_incident_report_review_and_alert_creation(): void
    {
        Storage::fake('public');
        $zone = $this->zoneIn('KH');
        $hazard = $this->hazardCode('sat_lo');
        $traveler = $this->traveler();

        $this->actingAs($traveler)->post('/vi/bao-su-co', [
            'zone_code' => $zone->code, 'hazard_code' => $hazard, 'title' => 'Sạt lở chắn đường đèo', 'lat' => 12.3, 'lng' => 109.1,
            // PNG 1×1 thật (image runtime không có GD nên không dùng fake()->image())
            'photo' => UploadedFile::fake()->createWithContent('anh.png', base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
            )),
        ])->assertSessionHasNoErrors();
        $incident = IncidentReport::sole();
        $this->assertNotNull($incident->photo);
        Storage::disk('public')->assertExists($incident->photo->path);

        $this->actingAs($traveler)->post('/vi/bao-su-co', ['zone_code' => $zone->code, 'hazard_code' => $hazard, 'title' => 'Ảnh giả',
            'photo' => UploadedFile::fake()->create('x.php', 10, 'application/x-php')])->assertSessionHasErrors('photo');

        $agency = $this->agency('KH');
        $this->actingAs($agency)->post("/vi/quan-tri/su-co/{$incident->id}/tao-canh-bao")->assertSessionHasErrors();
        $this->actingAs($this->agency('HN'))->post("/vi/quan-tri/su-co/{$incident->id}/duyet", ['status' => 'verified'])->assertForbidden();
        $this->actingAs($agency)->post("/vi/quan-tri/su-co/{$incident->id}/duyet", ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->actingAs($agency)->post("/vi/quan-tri/su-co/{$incident->id}/tao-canh-bao")->assertRedirect();

        $alert = Alert::sole();
        $this->assertSame(Alert::STATUS_DRAFT, $alert->status);
        $this->assertSame('Circle', $alert->geometry['type']);
        $this->assertSame('Sạt lở chắn đường đèo', $alert->headline);
    }

    public function test_resend_and_manual_broadcast(): void
    {
        $zone = $this->zoneIn('KH', ['code' => 'khanh-hoa']);
        $trip = $this->tripInZone($zone);
        $failed = Notification::factory()->failed()->create();
        $agency = $this->agency('KH');

        $this->actingAs($agency)->post("/vi/quan-tri/thong-bao/{$failed->id}/gui-lai")->assertRedirect();
        $this->assertSame(['queued', 0], [$failed->fresh()->status, $failed->fresh()->attempts]);

        $this->actingAs($agency)->post('/vi/quan-tri/thong-bao/phat-thu-cong', [
            'zone_codes' => ['khanh-hoa'], 'level' => 2, 'title' => 'Cấm biển chiều nay', 'body' => 'Không ra đảo sau 14h', 'channels' => ['inapp', 'sms'],
        ])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['inapp', 'sms'], Notification::where('user_id', $trip->user_id)->pluck('channel')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification.broadcast']);

        $other = $this->zoneIn('HN', ['code' => 'ha-noi']);
        $this->actingAs($agency)->post('/vi/quan-tri/thong-bao/phat-thu-cong', [
            'zone_codes' => [$other->code], 'level' => 1, 'title' => 'x', 'body' => 'y', 'channels' => ['inapp'],
        ])->assertForbidden();
        $this->actingAs($agency)->post('/vi/quan-tri/thong-bao/phat-thu-cong', ['zone_codes' => [], 'level' => 9, 'channels' => ['fax']])
            ->assertSessionHasErrors(['zone_codes', 'level', 'title', 'body', 'channels.0']);
    }

    public function test_agency_manages_only_contacts_of_own_province_and_guides_crud(): void
    {
        $agency = $this->agency('KH');
        $hnContact = EmergencyContact::create(['province_id' => FactoryHelpers::provinceId('HN'), 'contact_type' => 'police', 'phone' => '113', 'source_name' => 'x']);

        $this->actingAs($agency)->get("/vi/quan-tri/danh-ba/{$hnContact->id}/sua")->assertForbidden();
        $this->actingAs($agency)->post('/vi/quan-tri/danh-ba/tao', [
            'province_code' => 'HN', 'contact_type' => 'police', 'phone' => '113', 'source_name' => 'x', 'translations' => ['vi' => ['name' => 'X']],
        ])->assertForbidden();
        $this->actingAs($agency)->post('/vi/quan-tri/danh-ba/tao', [
            'province_code' => 'KH', 'contact_type' => 'tourist_support', 'phone' => '0258 3 000 000', 'source_name' => 'Sở Du lịch',
            'translations' => ['vi' => ['name' => 'Hỗ trợ du khách KH'], 'en' => ['name' => 'KH tourist support']],
        ])->assertSessionHasNoErrors();
        $contact = EmergencyContact::where('contact_type', 'tourist_support')->sole();
        $this->actingAs($agency)->post("/vi/quan-tri/danh-ba/{$contact->id}/xac-minh")->assertRedirect();
        $this->assertTrue($contact->fresh()->is_verified);

        $this->actingAs($agency)->post('/vi/quan-tri/huong-dan/tao', [
            'slug' => 'ung-pho-sat-lo', 'hazard_code' => $this->hazardCode('sat_lo'), 'min_severity' => 2, 'audience' => 'all', 'status' => 'draft',
            'translations' => ['vi' => ['title' => 'Ứng phó sạt lở', 'before_steps' => ['Theo dõi', ''], 'sources' => ['VDMA']]],
        ])->assertSessionHasNoErrors();
        $guide = ResponseGuide::sole();
        $this->assertSame(['Theo dõi'], $guide->translate('vi')->before_steps);
        $this->get('/vi/huong-dan-ung-pho/ung-pho-sat-lo')->assertNotFound();
        $this->actingAs($agency)->post("/vi/quan-tri/huong-dan/{$guide->id}/xuat-ban")->assertRedirect();
        $this->get('/vi/huong-dan-ung-pho/ung-pho-sat-lo')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Guides/Show', false)
            ->where('guide.before_steps', ['Theo dõi']));
    }

    public function test_frontend_action_urls(): void
    {
        $admin = $this->admin();
        $zone = $this->zoneIn('KH', ['code' => 'khanh-hoa']);
        $trip = $this->tripInZone($zone);
        $payload = [
            'intent' => 'publish', 'hazard_code' => $this->hazardCode('bao'), 'severity' => 3, 'zone_codes' => ['khanh-hoa'],
            'geometry' => null, 'starts_at' => now()->subHour()->format('Y-m-d\\TH:i'), 'ends_at' => null,
            'source_name' => 'Trung tâm hỗ trợ', 'source_url' => null, 'translations' => ['vi' => ['headline' => 'Bão']],
        ];

        $this->actingAs($admin)->post('/vi/quan-tri/canh-bao', $payload)->assertSessionHasNoErrors();
        $alert = Alert::sole();
        $this->assertSame(Alert::STATUS_ACTIVE, $alert->status);
        $this->assertSame(3, $trip->fresh()->risk_level_cached);
        $this->actingAs($admin)->put("/vi/quan-tri/canh-bao/{$alert->code}", array_merge($payload, ['intent' => 'update', 'severity' => 4]))->assertSessionHasNoErrors();
        $this->assertSame(Alert::STATUS_UPDATED, $alert->fresh()->status);
        $this->actingAs($admin)->post("/vi/quan-tri/canh-bao/{$alert->code}/het-hieu-luc")->assertSessionHasNoErrors();
        $this->assertSame(Alert::STATUS_EXPIRED, $alert->fresh()->status);
        $this->actingAs($admin)->post("/vi/quan-tri/canh-bao/{$alert->code}/phat-hanh")->assertSessionHasErrors('status');

        $failed = Notification::factory()->failed()->create();
        $this->actingAs($admin)->post('/vi/quan-tri/thong-bao/gui-lai', ['id' => $failed->id])->assertRedirect();
        $this->assertSame('queued', $failed->fresh()->status);

        $this->actingAs($admin)->put("/vi/quan-tri/vung/{$zone->code}", [
            'translations' => ['vi' => ['name' => 'Khánh Hòa mới'], 'en' => ['name' => 'New Khanh Hoa']],
            'geometry' => null, 'radius_km' => 30, 'centroid_lat' => 12.2, 'centroid_lng' => 109.1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Khánh Hòa mới', 'New Khanh Hoa'], [$zone->fresh()->name, $zone->fresh()->name_en]);

        $this->actingAs($admin)->get("/vi/quan-tri/canh-bao/tao?incident=999")->assertOk();
    }
}
