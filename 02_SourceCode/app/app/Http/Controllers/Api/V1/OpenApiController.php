<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

class OpenApiController extends Controller
{
    public function __invoke(): Response
    {
        $file = resource_path('openapi/openapi.json');
        abort_unless(is_file($file), 404);

        return response()->file($file, ['Content-Type' => 'application/json', 'Cache-Control' => 'public, max-age=300']);
    }
}
