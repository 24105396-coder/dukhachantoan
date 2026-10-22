<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\SafetyCheckin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Báo an toàn (F8): cần hỗ trợ → thông báo mức 4 tới cơ quan đúng tỉnh; liên kết chia sẻ theo token, giới hạn tần suất. */
class CheckinTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    public function test_need_help_notifies_agency_of_current_province_and_admin(): void
    {
        $trip = $this->tripInZone($this->zoneIn('KH'));
        $agencyKh = $this->agency('KH');
        $agencyHn = $this->agency('HN');
        $national = $this->agency(null);
        $admin = $this->admin();

        $this->actingAs($trip->user)->post("/vi/hanh-trinh/{$trip->code}/bao-an-toan", [
            'status' => 'need_help', 'message' => 'Kẹt xe do sạt lở', 'lat' => 12.25, 'lng' => 109.19,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $checkin = SafetyCheckin::sole();
        $this->assertSame('open', $checkin->response_status);
        $recipients = Notification::where('checkin_id', $checkin->id)->pluck('user_id')->unique()->values()->all();
        $this->assertEqualsCanonicalizing([$agencyKh->id, $national->id, $admin->id], $recipients);
        $this->assertNotContains($agencyHn->id, $recipients);
        $this->assertSame([4], Notification::where('checkin_id', $checkin->id)->pluck('level')->unique()->values()->all());

        // Cơ quan đúng tỉnh phản hồi; cơ quan tỉnh khác bị chặn
        $this->actingAs($agencyHn)->post("/vi/quan-tri/an-toan/{$checkin->id}/phan-hoi", ['response_status' => 'in_progress'])->assertForbidden();
        $this->actingAs($agencyKh)->post("/vi/quan-tri/an-toan/{$checkin->id}/phan-hoi", [
            'response_status' => 'closed', 'response_note' => 'Đã liên hệ cứu hộ',
        ])->assertSessionHasNoErrors();
        $this->assertSame($agencyKh->id, $checkin->fresh()->responded_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'checkin.respond', 'subject_type' => 'checkin', 'subject_id' => $checkin->id]);
        $this->assertTrue(Notification::where('user_id', $trip->user_id)->where('checkin_id', $checkin->id)->exists());
    }

    public function test_safe_checkin_does_not_alert_agencies_and_validates_input(): void
    {
        $trip = $this->tripInZone($this->zoneIn('KH'));
        $this->agency('KH');

        $this->actingAs($trip->user)->post("/vi/hanh-trinh/{$trip->code}/bao-an-toan", ['status' => 'maybe', 'lat' => 200])
            ->assertSessionHasErrors(['status', 'lat', 'lng']);
        $this->actingAs($trip->user)->post("/vi/hanh-trinh/{$trip->code}/bao-an-toan", ['status' => 'safe'])->assertSessionHasNoErrors();

        $this->assertSame('closed', SafetyCheckin::sole()->response_status);
        $this->assertSame(0, Notification::count());
    }

    public function test_shared_page_by_token_hides_contacts_and_wrong_token_is_404(): void
    {
        $trip = $this->tripInZone($this->zoneIn('KH'));

        $this->get("/vi/hanh-trinh/chia-se/{$trip->share_token}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Trips/Shared')
                ->where('trip.code', $trip->code)->has('trip.legs', 1)->missing('trip.contact_phone')->missing('trip.members')
                ->has('checkins')->has('alerts'));
        $this->get('/vi/hanh-trinh/chia-se/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/vi/hanh-trinh/chia-se/'.$trip->id)->assertNotFound();
        $this->getJson('/api/v1/trips/shared/'.str_repeat('Z', 40))->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->getJson("/api/v1/trips/shared/{$trip->share_token}")->assertOk()->assertJsonPath('data.code', $trip->code)
            ->assertJsonMissingPath('data.contact_phone');
    }

    public function test_checkin_by_token_is_rate_limited_to_ten_per_hour(): void
    {
        $trip = $this->tripInZone($this->zoneIn('KH'));
        $url = "/vi/hanh-trinh/chia-se/{$trip->share_token}/bao-an-toan";

        for ($i = 0; $i < 10; $i++) {
            $this->post($url, ['status' => 'safe', 'message' => "Lần $i"])->assertRedirect("/vi/hanh-trinh/chia-se/{$trip->share_token}");
        }
        $this->post($url, ['status' => 'safe'])->assertStatus(429);
        $this->postJson("/api/v1/trips/shared/{$trip->share_token}/checkin", ['status' => 'safe'])->assertStatus(429);

        $this->assertSame(10, SafetyCheckin::where('source', 'share_link')->count());
        $this->assertNull(SafetyCheckin::first()->user_id);
    }

    public function test_api_checkin_by_token_returns_checkin_item(): void
    {
        $trip = $this->tripInZone($this->zoneIn('KH'));
        $this->agency('KH');

        $this->postJson("/api/v1/trips/shared/{$trip->share_token}/checkin", ['status' => 'need_help', 'message' => 'SOS'])
            ->assertCreated()->assertJsonPath('data.status', 'need_help')->assertJsonPath('data.source', 'share_link');
        $this->postJson("/api/v1/trips/shared/{$trip->share_token}/checkin", ['status' => 'x'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame(1, Notification::where('level', 4)->distinct('user_id')->count('user_id'));
    }
}
