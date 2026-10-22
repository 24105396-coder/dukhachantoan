<?php

namespace App\Http\Controllers;

use App\Services\LookupService;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    public function __invoke(LookupService $lookup): Response
    {
        return Inertia::render('About', ['stats' => $lookup->stats()]);
    }
}
