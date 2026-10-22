<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Trip;
use App\Models\User;
use App\Models\Zone;
use App\Support\RelativeDate;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/**
 * Hành trình mẫu từ database/data/trips_seed.json (idempotent theo code). Chặng ghi theo leg_order,
 * thành viên tạo lại mỗi lần seed. Dữ liệu cá nhân là mô phỏng.
 */
class TripSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $users = User::pluck('id', 'email');
        $zones = Zone::query()->get(['id', 'code', 'centroid_lat', 'centroid_lng'])->keyBy('code');
        $destinations = Destination::query()->get(['id', 'slug', 'lat', 'lng'])->keyBy('slug');

        foreach ($this->readJson('trips_seed.json') as $row) {
            $ownerId = $users[$row['owner_email'] ?? ''] ?? null;
            if (empty($row['code']) || ! $ownerId) {
                $this->warnSeed('bỏ qua hành trình thiếu code hoặc owner_email không tồn tại: '.($row['code'] ?? '?'));

                continue;
            }
            $trip = Trip::updateOrCreate(['code' => $row['code']], [
                'user_id' => $ownerId,
                'partner_id' => $users[$row['partner_email'] ?? ''] ?? null,
                'title' => $row['title'] ?? $row['code'],
                'start_date' => RelativeDate::date($row['start'] ?? '+0d'),
                'end_date' => RelativeDate::date($row['end'] ?? $row['start'] ?? '+0d'),
                'group_size' => (int) ($row['group_size'] ?? max(1, count($row['members'] ?? []))),
                'nationality' => $row['nationality'] ?? null,
                'preferred_locale' => $row['preferred_locale'] ?? 'vi',
                'contact_name' => $row['contact']['name'] ?? null,
                'contact_phone' => $row['contact']['phone'] ?? null,
                'contact_email' => $row['contact']['email'] ?? null,
                'status' => $row['status'] ?? Trip::STATUS_ACTIVE,
            ]);
            $this->syncLegs($trip, $row['legs'] ?? [], $zones, $destinations);
            $this->syncMembers($trip, $row['members'] ?? []);
        }
    }

    private function syncLegs(Trip $trip, array $legs, $zones, $destinations): void
    {
        $order = 0;
        foreach ($legs as $leg) {
            $zone = $zones[$leg['zone_code'] ?? ''] ?? null;
            if (! $zone) {
                $this->warnSeed("{$trip->code}: bỏ qua chặng có zone_code không tồn tại ".($leg['zone_code'] ?? '?'));

                continue;
            }
            $destination = $destinations[$leg['destination_slug'] ?? ''] ?? null;
            $trip->legs()->updateOrCreate(['leg_order' => ++$order], [
                'zone_id' => $zone->id,
                'destination_id' => $destination?->id,
                'title' => $leg['title'] ?? null,
                'starts_at' => $this->when($leg['starts_at'] ?? null),
                'ends_at' => $this->when($leg['ends_at'] ?? null),
                'lodging' => $leg['lodging'] ?? null,
                'transport' => $leg['transport'] ?? null,
                'lat' => $leg['lat'] ?? $destination?->lat,
                'lng' => $leg['lng'] ?? $destination?->lng,
                'note' => $leg['note'] ?? null,
            ]);
        }
        $trip->legs()->where('leg_order', '>', $order)->delete();
    }

    private function syncMembers(Trip $trip, array $members): void
    {
        $trip->members()->delete();
        foreach ($members as $member) {
            if (empty($member['full_name'])) {
                continue;
            }
            $trip->members()->create([
                'full_name' => $member['full_name'],
                'phone' => $member['phone'] ?? null,
                'nationality' => $member['nationality'] ?? null,
                'age_group' => $member['age_group'] ?? 'adult',
                'special_needs' => $member['special_needs'] ?? null,
                'is_leader' => (bool) ($member['is_leader'] ?? false),
            ]);
        }
    }
}
