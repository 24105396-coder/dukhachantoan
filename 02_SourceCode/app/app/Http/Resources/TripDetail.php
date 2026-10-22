<?php

namespace App\Http\Resources;

use App\Models\AlertMatch;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * TripDetail = TripCard + liên hệ, chặng, thành viên, đối sánh, check-in, thông báo và quyền thao tác.
 * $viewer quyết định: thông báo của chính người xem; cơ quan/quản trị thấy số điện thoại thành viên đầy đủ.
 *
 * @mixin Trip
 */
class TripDetail extends BaseResource
{
    public const MAX_CHECKINS = 20;
    public const MAX_NOTIFICATIONS = 20;

    public function toArray(Request $request): array
    {
        $trip = $this->resource;
        /** @var User|null $viewer */
        $viewer = $request->user();
        $isStaff = (bool) $viewer?->isStaff();

        $matches = $trip->matches->filter(fn (AlertMatch $m) => $m->alert !== null)
            ->sortByDesc(fn (AlertMatch $m) => [$m->alert->isLive() ? 1 : 0, $m->alert->severity])->values();
        $notifications = $viewer
            ? $trip->notifications()->with('alert.translations', 'trip')->where('user_id', $viewer->id)
                ->where('channel', 'inapp')->latest('id')->limit(self::MAX_NOTIFICATIONS)->get()
            : collect();

        return (new TripCard($trip))->toArray($request) + [
            'nationality' => $trip->nationality,
            'preferred_locale' => $trip->preferred_locale,
            'contact_name' => $trip->contact_name,
            'contact_phone' => $trip->contact_phone,
            'contact_email' => $trip->contact_email,
            'legs' => LegItem::list($trip->legs),
            'members' => $trip->members->map(fn ($m) => (new MemberItem($m))->withFullPhone($isStaff)->resolve($request))->values()->all(),
            'matches' => MatchItem::list($matches),
            'checkins' => CheckinItem::list($trip->checkins->sortByDesc('checked_at')->take(self::MAX_CHECKINS)),
            'notifications' => NotificationItem::list($notifications),
            'ical_url' => localized_route('trips.ical', ['trip' => $trip->code]),
            'can_edit' => (bool) $viewer?->can('update', $trip),
            'can_cancel' => (bool) $viewer?->can('cancel', $trip),
            'can_checkin' => (bool) $viewer?->can('checkin', $trip),
        ];
    }
}
