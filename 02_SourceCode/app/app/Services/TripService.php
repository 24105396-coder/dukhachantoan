<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\AlertMatch;
use App\Models\Destination;
use App\Models\Trip;
use App\Models\User;
use App\Models\Zone;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;

/**
 * Hành trình (F1): tạo/sửa/hủy, chặng và thành viên, đối sánh lại với cảnh báo hiệu lực sau mỗi thay đổi,
 * xếp thông báo cho đối sánh mới; mã TR- và share_token sinh ngẫu nhiên trong model.
 */
class TripService
{
    public const EDITABLE_FIELDS = [
        'title', 'start_date', 'end_date', 'group_size', 'nationality', 'preferred_locale', 'contact_name', 'contact_phone', 'contact_email',
    ];

    public function __construct(
        private readonly MatchingService $matching,
        private readonly NotificationService $notifications,
        private readonly RiskService $risk,
        private readonly AuditLogger $audit,
        private readonly IcalBuilder $ical,
    ) {
    }

    /** Đơn vị lữ hành tạo hành trình cho đoàn: vừa là chủ vừa là partner_id. */
    public function create(User $actor, array $data): Trip
    {
        $trip = DB::transaction(function () use ($actor, $data) {
            $trip = Trip::create($this->fields($data) + [
                'user_id' => $actor->id,
                'partner_id' => $actor->isPartner() ? $actor->id : null,
                'status' => ($data['status'] ?? null) === Trip::STATUS_DRAFT ? Trip::STATUS_DRAFT : Trip::STATUS_ACTIVE,
            ]);
            $this->syncLegs($trip, $data['legs'] ?? []);
            $this->syncMembers($trip, $data['members'] ?? []);

            return $trip;
        });
        $result = $this->afterSave($trip);
        $this->audit->log('trip.create', $trip, ['legs' => count($data['legs'] ?? [])] + $result, $actor);

        return $trip;
    }

    public function update(Trip $trip, array $data, User $actor): Trip
    {
        if (! $trip->isEditable()) {
            throw new BusinessRuleException(__('messages.trips.not_editable'));
        }
        DB::transaction(function () use ($trip, $data) {
            $status = $data['status'] ?? null;
            $trip->fill($this->fields($data));
            if (in_array($status, [Trip::STATUS_DRAFT, Trip::STATUS_ACTIVE], true)) {
                $trip->status = $status;
            }
            $trip->save();
            $this->syncLegs($trip, $data['legs'] ?? []);
            $this->syncMembers($trip, $data['members'] ?? []);
        });
        $result = $this->afterSave($trip->refresh());
        $this->audit->log('trip.update', $trip, $result, $actor);

        return $trip;
    }

    /** Hủy: dừng thông báo đang xếp hàng, đối sánh → resolved, mức rủi ro về 0. */
    public function cancel(Trip $trip, User $actor): Trip
    {
        if (! $trip->isEditable()) {
            throw new BusinessRuleException(__('messages.trips.not_cancellable'));
        }
        $cancelled = DB::transaction(function () use ($trip) {
            $trip->forceFill(['status' => Trip::STATUS_CANCELLED, 'cancelled_at' => now(), 'risk_level_cached' => 0])->save();
            AlertMatch::query()->where('trip_id', $trip->id)->where('status', '!=', AlertMatch::STATUS_RESOLVED)
                ->update(['status' => AlertMatch::STATUS_RESOLVED, 'updated_at' => now()]);

            return $this->notifications->cancelQueuedForTrip($trip);
        });
        $this->risk->forgetSummary();
        $this->audit->log('trip.cancel', $trip, ['notifications_cancelled' => $cancelled], $actor);

        return $trip;
    }

    /** Du khách xác nhận đã biết cảnh báo của chặng. */
    public function acknowledge(Trip $trip, AlertMatch $match): AlertMatch
    {
        if ($match->trip_id !== $trip->id) {
            abort(404);
        }
        if ($match->status !== AlertMatch::STATUS_RESOLVED) {
            $match->forceFill(['status' => AlertMatch::STATUS_ACKNOWLEDGED, 'acknowledged_at' => now()])->save();
        }

        return $match;
    }

    public function ical(Trip $trip): string
    {
        return $this->ical->trip($trip);
    }

    /** Đối sánh lại + xếp thông báo. @return array{matches:int, notifications:int} */
    public function afterSave(Trip $trip): array
    {
        $matches = $this->matching->matchTrip($trip);
        $created = $this->notifications->enqueueMatches($matches);
        $this->risk->forgetSummary();

        return ['matches' => $matches->count(), 'notifications' => $created];
    }

    private function fields(array $data): array
    {
        $fields = array_intersect_key($data, array_flip(self::EDITABLE_FIELDS));
        if (array_key_exists('group_size', $data) || isset($data['members'])) {
            $fields['group_size'] = (int) ($data['group_size'] ?? 0) ?: max(1, count($data['members'] ?? []));
        }

        return $fields;
    }

    /** Chặng sắp theo giờ bắt đầu, đánh leg_order 1..n; cập nhật theo leg_order để giữ id, xóa chặng thừa. */
    private function syncLegs(Trip $trip, array $legs): void
    {
        $legs = collect($legs)->sortBy(fn ($l) => (string) ($l['starts_at'] ?? ''))->values();
        $zones = Zone::query()->whereIn('code', $legs->pluck('zone_code')->filter())->get()->keyBy('code');
        $destinations = Destination::query()->whereIn('slug', $legs->pluck('destination_slug')->filter())->get()->keyBy('slug');
        // Tránh trùng unique(trip_id, leg_order) khi đảo thứ tự: dời tạm các chặng cũ ra khỏi dải 1..n
        $trip->legs()->update(['leg_order' => DB::raw('leg_order + 1000')]);
        foreach ($legs as $index => $leg) {
            $destination = $destinations[$leg['destination_slug'] ?? ''] ?? null;
            $zone = $zones[$leg['zone_code'] ?? ''] ?? $destination?->zone;
            $existing = $trip->legs()->where('leg_order', $index + 1001)->first();
            $attributes = [
                'leg_order' => $index + 1,
                'zone_id' => $zone->id,
                'destination_id' => $destination?->id,
                'title' => $leg['title'] ?? null,
                'starts_at' => LocalTime::parse($leg['starts_at']),
                'ends_at' => LocalTime::parse($leg['ends_at']),
                'lodging' => $leg['lodging'] ?? null,
                'transport' => $leg['transport'] ?? null,
                'note' => $leg['note'] ?? null,
                'lat' => $leg['lat'] ?? $destination?->lat,
                'lng' => $leg['lng'] ?? $destination?->lng,
            ];
            $existing ? $existing->update($attributes) : $trip->legs()->create($attributes);
        }
        $trip->legs()->where('leg_order', '>', 1000)->delete();
        $trip->unsetRelation('legs');
    }

    private function syncMembers(Trip $trip, array $members): void
    {
        $trip->members()->delete();
        foreach ($members as $i => $member) {
            if (empty($member['full_name'])) {
                continue;
            }
            $trip->members()->create([
                'full_name' => $member['full_name'],
                'phone' => $member['phone'] ?? null,
                'nationality' => $member['nationality'] ?? null,
                'age_group' => $member['age_group'] ?? 'adult',
                'special_needs' => $member['special_needs'] ?? null,
                'is_leader' => (bool) ($member['is_leader'] ?? $i === 0),
            ]);
        }
        $trip->unsetRelation('members');
    }
}
