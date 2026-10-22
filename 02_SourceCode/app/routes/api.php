<?php

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// API mở v1: JSON, không cần đăng nhập, giới hạn 60 yêu cầu/phút/IP (throttle:api)
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('healthz', HealthController::class)->name('healthz');
    Route::get('stats/overview', V1\StatsController::class)->name('stats.overview');
    Route::get('openapi.json', V1\OpenApiController::class)->name('openapi');
});
