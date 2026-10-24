<?php

namespace App\Http\Controllers;

use App\Services\TravelerRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Du khách theo dõi sự cố đã báo và yêu cầu "cần hỗ trợ" (trạng thái xử lý của cơ quan). */
class RequestController extends Controller
{
    public function index(Request $request, TravelerRequestService $requests): Response
    {
        return Inertia::render('Requests/Index', $requests->overview($request->user()) + [
            'highlight' => TravelerRequestService::highlight($request->query('moi')),
        ]);
    }
}
