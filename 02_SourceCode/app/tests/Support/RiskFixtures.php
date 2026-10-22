<?php

namespace Tests\Support;

use App\Models\Alert;
use App\Models\Trip;
use App\Models\TripLeg;
use App\Models\User;
use App\Models\Zone;
use Database\Factories\FactoryHelpers;

/** Dựng nhanh vùng – hành trình – chặng – cảnh báo cho test nghiệp vụ. */
trait RiskFixtures
{
    protected function zoneIn(string $province = 'KH', array $attributes = []): Zone
    {
        return Zone::factory()->inProvince($province)->create($attributes);
    }

    /** Hành trình active có một chặng trong vùng $zone, đang diễn ra (từ 1 giờ trước tới 1 ngày sau). */
    protected function tripInZone(Zone $zone, ?User $owner = null, array $legAttributes = []): Trip
    {
        $trip = Trip::factory()->create([
            'user_id' => ($owner ?? User::factory()->traveler()->create(['phone' => '0912345678']))->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);
        TripLeg::factory()->create(array_merge([
            'trip_id' => $trip->id,
            'zone_id' => $zone->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'lat' => $zone->centroid_lat,
            'lng' => $zone->centroid_lng,
        ], $legAttributes));

        return $trip->fresh();
    }

    /** Cảnh báo theo vùng (không geometry) ở trạng thái cho trước. */
    protected function alertForZones(array $zones, int $severity = 3, string $status = Alert::STATUS_ACTIVE, array $attributes = []): Alert
    {
        $alert = Alert::factory()->severity($severity)->create(array_merge(['status' => $status], $attributes));
        $alert->zones()->sync(collect($zones)->pluck('id')->all());

        return $alert->fresh();
    }

    protected function hazardCode(string $code = 'bao'): string
    {
        FactoryHelpers::hazardTypeId($code);

        return $code;
    }
}
