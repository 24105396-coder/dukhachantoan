<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TourRoute;
use App\Services\IcalBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IcalController extends ApiController
{
    public function __invoke(Request $request, string $slug, IcalBuilder $ical): Response
    {
        $this->applyLocale($request);
        $route = TourRoute::published()->where('slug', $slug)->with('translations')->firstOrFail();
        $slots = $route->slots()->upcoming()->limit(200)->get();

        return response($ical->route($route, $slots), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$route->slug.'.ics"',
        ]);
    }
}
