<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Role;
use App\Models\SafetyCheckin;
use App\Models\Trip;
use App\Models\TripLeg;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Báo tình trạng an toàn (F8). "Cần hỗ trợ" → thông báo mức 4 (mọi kênh) tới cơ quan hỗ trợ của tỉnh
 * nơi du khách đang ở (chặng hiện tại, không rõ thì mọi tỉnh của hành trình), cơ quan toàn quốc và admin.
 */
class CheckinService
{
    public function __construct(private readonly NotificationService $notifications, private readonly AuditLogger $audit)
    {
    }

    /** $data: status safe|need_help, message?, lat?, lng?, accuracy_m?, alert_code?; $source: app|share_link|agency. */
    public function create(Trip $trip, ?User $user, array $data, string $source = 'app'): SafetyCheckin
    {
        $status = $data['status'];
        $checkin = SafetyCheckin::create([
            'trip_id' => $trip->id,
            'user_id' => $user?->id,
            'alert_id' => ! empty($data['alert_code']) ? Alert::query()->where('code', $data['alert_code'])->value('id') : null,
            'status' => $status,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'accuracy_m' => $data['accuracy_m'] ?? null,
            'message' => $data['message'] ?? null,
            'locale' => app()->getLocale(),
            'source' => $source,
            'checked_at' => now(),
            'response_status' => $status === SafetyCheckin::STATUS_NEED_HELP ? 'open' : 'closed',
        ]);
        if ($status === SafetyCheckin::STATUS_NEED_HELP) {
            $count = $this->alertResponders($checkin, $trip);
            Log::channel('nghiepvu')->warning('Du khách cần hỗ trợ', ['trip' => $trip->code, 'checkin_id' => $checkin->id, 'notifications' => $count]);
        }
        $this->audit->log('checkin.create', $checkin, ['status' => $status, 'source' => $source], $user);

        return $checkin;
    }

    /** Cơ quan phản hồi check-in: trạng thái xử lý + ghi chú; báo lại cho chủ hành trình (mức 2, in-app). */
    public function respond(SafetyCheckin $checkin, User $actor, string $status, ?string $note): SafetyCheckin
    {
        $checkin->forceFill([
            'response_status' => $status,
            'response_note' => $note,
            'responded_by' => $actor->id,
            'responded_at' => now(),
        ])->save();
        $trip = $checkin->trip()->with('user')->first();
        if ($trip?->user) {
            $locale = $this->notifications->localeFor($trip->user, $trip);
            $this->notifications->queueFor($trip->user, 2, [
                'locale' => $locale,
                'title' => __('messages.notify.checkin_response_title', ['trip' => $trip->title], $locale),
                'body' => trim(__('enums.response_statuses.'.$status, [], $locale).'. '.(string) $note),
                'action_url' => localized_route('trips.show', ['locale' => $locale, 'trip' => $trip->code]),
            ], ['trip_id' => $trip->id, 'checkin_id' => $checkin->id], ['inapp']);
        }
        $this->audit->log('checkin.respond', $checkin, ['response_status' => $status], $actor);

        return $checkin;
    }

    /** Người nhận cảnh báo "cần hỗ trợ": agency đúng tỉnh + agency toàn quốc + admin. @return Collection<int, User> */
    public function responders(Trip $trip): Collection
    {
        $provinceIds = $this->currentProvinceIds($trip);

        return User::query()->where('is_active', true)->whereHas('role', fn ($r) => $r->whereIn('code', [Role::AGENCY, Role::ADMIN]))
            ->with('role', 'notificationPreference')
            ->get()
            ->filter(fn (User $u) => $u->isAdmin() || $u->province_id === null || in_array((int) $u->province_id, $provinceIds, true))
            ->values();
    }

    private function alertResponders(SafetyCheckin $checkin, Trip $trip): int
    {
        $count = 0;
        foreach ($this->responders($trip) as $user) {
            $locale = $user->locale === 'en' ? 'en' : 'vi';
            $count += $this->notifications->queueFor($user, 4, [
                'locale' => $locale,
                'title' => __('messages.notify.need_help_title', ['trip' => $trip->title, 'code' => $trip->code], $locale),
                'body' => __('messages.notify.need_help_body', [
                    'contact' => trim(($trip->contact_name ?? '').' '.($trip->contact_phone ?? '')),
                    'message' => (string) $checkin->message,
                    'position' => $checkin->lat !== null ? sprintf('%.5f, %.5f', $checkin->lat, $checkin->lng) : '—',
                ], $locale),
                'action_url' => localized_route('admin.checkins.index', ['locale' => $locale, 'status' => 'need_help']),
                'payload' => ['kind' => 'need_help'],
            ], ['trip_id' => $trip->id, 'checkin_id' => $checkin->id])->count();
        }

        return $count;
    }

    /** Tỉnh của chặng đang diễn ra (theo giờ hiện tại); không có thì mọi tỉnh hành trình đi qua. */
    private function currentProvinceIds(Trip $trip): array
    {
        $now = now();
        $current = $trip->legs()->with('zone')->get()
            ->first(fn (TripLeg $leg) => $leg->starts_at?->lte($now) && ($leg->ends_at === null || $leg->ends_at->gte($now)));
        if ($current?->zone?->province_id) {
            return [(int) $current->zone->province_id];
        }

        return $trip->provinceIds();
    }
}
