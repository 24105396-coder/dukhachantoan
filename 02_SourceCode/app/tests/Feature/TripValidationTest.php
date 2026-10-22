<?php

namespace Tests\Feature;

use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Kiểm tra hợp lệ đăng ký hành trình (F1) ở máy chủ và luồng tạo/sửa/hủy. */
class TripValidationTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    private ?\App\Models\Zone $zone = null;

    private function payload(array $overrides = []): array
    {
        $zone = $this->zone ??= $this->zoneIn('KH', ['code' => 'nha-trang-test']);
        $day = now()->addDay()->startOfDay();

        return array_merge([
            'title' => 'Nha Trang 3 ngày',
            'start_date' => $day->toDateString(),
            'end_date' => $day->copy()->addDays(2)->toDateString(),
            'group_size' => 2,
            'preferred_locale' => 'vi',
            'contact_phone' => '0912 345 678',
            'legs' => [
                ['zone_code' => $zone->code, 'starts_at' => $day->copy()->setTime(9, 0)->toDateTimeString(), 'ends_at' => $day->copy()->setTime(18, 0)->toDateTimeString()],
                ['zone_code' => $zone->code, 'starts_at' => $day->copy()->addDay()->setTime(8, 0)->toDateTimeString(), 'ends_at' => $day->copy()->addDay()->setTime(20, 0)->toDateTimeString()],
            ],
            'members' => [['full_name' => 'Nguyễn Văn A', 'phone' => '0987654321', 'age_group' => 'adult']],
        ], $overrides);
    }

    public function test_overlapping_legs_are_rejected(): void
    {
        $data = $this->payload();
        $data['legs'][1]['starts_at'] = now()->addDay()->startOfDay()->setTime(17, 0)->toDateTimeString();

        $this->actingAs($this->traveler())->post('/vi/hanh-trinh/tao', $data)->assertSessionHasErrors('legs.1.starts_at');
        $this->assertSame(0, Trip::count());
    }

    public function test_leg_outside_trip_dates_is_rejected(): void
    {
        $data = $this->payload();
        $data['legs'][1]['starts_at'] = now()->addDays(5)->setTime(8, 0)->toDateTimeString();
        $data['legs'][1]['ends_at'] = now()->addDays(5)->setTime(9, 0)->toDateTimeString();

        $this->actingAs($this->traveler())->post('/vi/hanh-trinh/tao', $data)->assertSessionHasErrors('legs.1.starts_at');
    }

    public function test_trip_without_legs_and_bad_fields_is_rejected(): void
    {
        $data = $this->payload(['legs' => [], 'end_date' => now()->subYear()->toDateString(), 'contact_phone' => 'abc<script>']);

        $this->actingAs($this->traveler())->post('/vi/hanh-trinh/tao', $data)
            ->assertSessionHasErrors(['legs', 'end_date', 'contact_phone']);
        $data = $this->payload();
        $data['legs'][0]['zone_code'] = 'khong-ton-tai';
        $data['legs'][0]['ends_at'] = $data['legs'][0]['starts_at'];
        $this->actingAs($this->traveler())->post('/vi/hanh-trinh/tao', $data)->assertSessionHasErrors('legs.0.zone_code');
    }

    public function test_valid_trip_is_created_with_random_code_and_share_token(): void
    {
        $traveler = $this->traveler();
        $response = $this->actingAs($traveler)->post('/vi/hanh-trinh/tao', $this->payload());

        $trip = Trip::with('legs', 'members')->sole();
        $response->assertRedirect("/vi/hanh-trinh/{$trip->code}");
        $this->assertMatchesRegularExpression('/^TR-[A-Z0-9]{8}$/', $trip->code);
        $this->assertSame(40, strlen($trip->share_token));
        $this->assertSame([1, 2], $trip->legs->pluck('leg_order')->all());
        $this->assertSame('Nguyễn Văn A', $trip->members->first()->full_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'trip.create', 'subject_type' => 'trip', 'subject_id' => $trip->id]);

        $this->actingAs($traveler)->get("/vi/hanh-trinh/{$trip->code}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Trips/Show')
                ->where('trip.code', $trip->code)
                ->has('trip.legs', 2)
                ->where('trip.members.0.phone_masked', '098****321')
                ->missing('trip.members.0.phone')
                ->where('trip.can_edit', true)
                ->has('trip.ical_url')
                ->where('zonesGeo.type', 'FeatureCollection'));
    }

    public function test_partner_trip_sets_partner_id_and_cancel_stops_queue(): void
    {
        $partner = $this->partner();
        $this->actingAs($partner)->post('/vi/hanh-trinh/tao', $this->payload())->assertSessionHasNoErrors();
        $trip = Trip::sole();
        $this->assertSame($partner->id, $trip->partner_id);

        $this->actingAs($partner)->post("/vi/hanh-trinh/{$trip->code}/huy")->assertRedirect();
        $this->assertSame(Trip::STATUS_CANCELLED, $trip->fresh()->status);
        $this->actingAs($partner)->get("/vi/hanh-trinh/{$trip->code}/sua")->assertForbidden();
    }

    public function test_ical_export_lists_legs(): void
    {
        $traveler = $this->traveler();
        $this->actingAs($traveler)->post('/vi/hanh-trinh/tao', $this->payload());
        $trip = Trip::sole();

        $response = $this->actingAs($traveler)->get("/vi/hanh-trinh/{$trip->code}/lich.ics")->assertOk();
        $this->assertStringContainsString('text/calendar', $response->headers->get('Content-Type'));
        $this->assertSame(2, substr_count($response->getContent(), 'BEGIN:VEVENT'));
    }
}
