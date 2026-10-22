<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckinRequest;
use App\Http\Resources\AlertCard;
use App\Http\Resources\CheckinItem;
use App\Http\Resources\LegItem;
use App\Http\Resources\TripCard;
use App\Models\Alert;
use App\Models\Trip;
use App\Services\CheckinService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trang chia sẻ hành trình theo share_token 40 ký tự (không đăng nhập): trạng thái chặng, cảnh báo, 3 check-in gần nhất;
 * không lộ liên hệ, thành viên. Báo an toàn qua token: giới hạn 10 lần/giờ/token.
 */
class SharedTripController extends Controller
{
    public const RECENT_CHECKINS = 3;

    public function show(string $token): Response
    {
        $trip = self::resolve($token);

        return Inertia::render('Trips/Shared', self::payload($trip) + ['token' => $token]);
    }

    public function checkinForm(string $token): Response
    {
        $trip = self::resolve($token);

        return Inertia::render('Trips/SharedCheckin', [
            'trip' => ['code' => $trip->code, 'title' => $trip->title, 'status' => $trip->status],
            'token' => $token,
            'statuses' => $this->enumOptions('checkin_statuses', ['safe', 'need_help']),
            'alerts' => AlertCard::list(self::liveAlerts($trip)),
        ]);
    }

    public function checkin(CheckinRequest $request, CheckinService $checkins, string $token): RedirectResponse
    {
        $trip = self::resolve($token);
        abort_if($trip->status === Trip::STATUS_CANCELLED, 404);
        $checkins->create($trip, $request->user(), $request->validated(), 'share_link');

        return redirect(localized_route('trips.shared', ['token' => $token]))->with('success', __('messages.checkins.recorded'));
    }

    /** Token sai định dạng hoặc không tồn tại → 404 (không phân biệt để tránh dò). */
    public static function resolve(string $token): Trip
    {
        abort_unless(strlen($token) === Trip::SHARE_TOKEN_LENGTH && ctype_alnum($token), 404);

        return Trip::query()->withCardData()->where('share_token', $token)
            ->whereIn('status', [Trip::STATUS_ACTIVE, Trip::STATUS_COMPLETED, Trip::STATUS_DRAFT])->firstOrFail();
    }

    /** Dữ liệu công khai dùng chung trang web và API. */
    public static function payload(Trip $trip): array
    {
        $trip->load(['legs.matches.alert' => fn ($q) => $q->with(AlertCard::RELATIONS)]);
        $card = TripCard::one($trip);
        unset($card['share_url']);

        return [
            'trip' => $card + ['legs' => LegItem::list($trip->legs)],
            'checkins' => CheckinItem::list($trip->checkins()->with('alert.translations', 'user:id,name', 'responder:id,name')
                ->latest('checked_at')->limit(self::RECENT_CHECKINS)->get()),
            'alerts' => AlertCard::list(self::liveAlerts($trip)),
        ];
    }

    private static function liveAlerts(Trip $trip)
    {
        return Alert::query()->live()->with(AlertCard::RELATIONS)
            ->whereHas('matches', fn ($q) => $q->where('trip_id', $trip->id)->where('status', '!=', 'resolved'))
            ->mostSevere()->get();
    }
}
