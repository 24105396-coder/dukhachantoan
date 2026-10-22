<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\SharedTripController as WebSharedTrip;
use App\Http\Requests\CheckinRequest;
use App\Http\Resources\CheckinItem;
use App\Models\Trip;
use App\Services\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API hành trình chia sẻ theo token (không lộ liên hệ) và báo an toàn qua token (10 lần/giờ/token). */
class SharedTripController extends ApiController
{
    public function show(Request $request, string $token): JsonResponse
    {
        $this->applyLocale($request);
        $payload = WebSharedTrip::payload(WebSharedTrip::resolve($token));

        return response()->json(['data' => $payload['trip'] + ['checkins' => $payload['checkins'], 'alerts' => $payload['alerts']]]);
    }

    public function checkin(CheckinRequest $request, CheckinService $checkins, string $token): JsonResponse
    {
        $this->applyLocale($request);
        $trip = WebSharedTrip::resolve($token);
        abort_if($trip->status === Trip::STATUS_CANCELLED, 404);
        $checkin = $checkins->create($trip, null, $request->validated(), 'share_link');
        $item = CheckinItem::one($checkin->load('alert.translations', 'user', 'responder'));

        return response()->json(['data' => $item], 201);
    }
}
