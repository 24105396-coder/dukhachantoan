<?php

namespace App\Http\Controllers;

use App\Models\AlertSource;
use App\Services\LookupService;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    public function __invoke(LookupService $lookup): Response
    {
        $sources = AlertSource::query()->where('is_active', true)->orderBy('id')->get()
            ->map(fn (AlertSource $s) => [
                'code' => $s->code,
                'name' => $s->name,
                'provider' => $s->provider,
                'url' => $s->base_url,
                'attribution' => $s->attribution,
                'license' => $s->license,
            ])->values()->all();

        return Inertia::render('About', [
            'sources' => $sources,
            'team' => config('dukhach.academic.team'),
            'stats' => $lookup->stats(),
        ]);
    }
}
