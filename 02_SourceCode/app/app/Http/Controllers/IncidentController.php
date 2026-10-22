<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncidentRequest;
use App\Models\Trip;
use App\Services\IncidentService;
use App\Services\LookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Du khách báo sự cố thực địa (ảnh tùy chọn). */
class IncidentController extends Controller
{
    public function create(Request $request, LookupService $lookup): Response
    {
        return Inertia::render('Incidents/Form', [
            'hazards' => $lookup->hazards(),
            'zones' => $lookup->zones(),
            'trips' => Trip::query()->visibleTo($request->user())->ongoing()->orderBy('start_date')->limit(20)
                ->get(['code', 'title'])->map(fn (Trip $t) => ['code' => $t->code, 'title' => $t->title])->all(),
        ]);
    }

    public function store(IncidentRequest $request, IncidentService $incidents): RedirectResponse
    {
        $incidents->report($request->user(), $request->safe()->except('photo'), $request->file('photo'));

        return redirect(localized_route('incidents.create'))->with('success', __('messages.incidents.reported'));
    }
}
