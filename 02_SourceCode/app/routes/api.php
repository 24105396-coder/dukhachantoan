<?php

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// API mở v1: JSON, không cần đăng nhập, giới hạn 60 yêu cầu/phút/IP (throttle:api)
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('healthz', HealthController::class)->name('healthz');
    Route::get('stats/overview', V1\StatsController::class)->name('stats.overview');
    Route::get('openapi.json', V1\OpenApiController::class)->name('openapi');

    Route::get('alerts', [V1\AlertController::class, 'index'])->name('alerts.index');
    Route::get('alerts.geojson', [V1\AlertController::class, 'geojson'])->name('alerts.geojson');
    Route::get('alerts/{alert}', [V1\AlertController::class, 'show'])->name('alerts.show');
    Route::get('zones', [V1\ZoneController::class, 'index'])->name('zones.index');
    Route::get('zones.geojson', [V1\ZoneController::class, 'geojson'])->name('zones.geojson');
    Route::get('zones/{zone}/weather', [V1\ZoneController::class, 'weather'])->name('zones.weather');
    Route::get('contacts', V1\ContactController::class)->name('contacts.index');
    Route::get('guides', [V1\GuideController::class, 'index'])->name('guides.index');
    Route::get('guides/{guide}', [V1\GuideController::class, 'show'])->name('guides.show');
    Route::get('trips/shared/{token}', [V1\SharedTripController::class, 'show'])->name('trips.shared');
    Route::post('trips/shared/{token}/checkin', [V1\SharedTripController::class, 'checkin'])
        ->middleware('throttle:checkin-token')->name('trips.shared.checkin');
});
