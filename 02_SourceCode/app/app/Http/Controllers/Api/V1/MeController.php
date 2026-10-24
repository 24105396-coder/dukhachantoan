<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\TravelerRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API của người dùng đã đăng nhập (phiên cookie cùng nguồn): yêu cầu đã gửi và trạng thái xử lý. */
class MeController extends ApiController
{
    public function requests(Request $request, TravelerRequestService $requests): JsonResponse
    {
        $this->applyLocale($request);
        $overview = $requests->overview($request->user());

        return response()->json([
            'data' => ['incidents' => $overview['incidents'], 'checkins' => $overview['checkins']],
            'counts' => $overview['counts'],
            'meta' => $overview['pagination'] + ['locale' => app()->getLocale()],
        ]);
    }
}
