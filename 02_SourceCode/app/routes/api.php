<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

// API mở v1: JSON, không cần đăng nhập, giới hạn 60 yêu cầu/phút/IP (throttle:api), CORS *
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('sites', [V1\SiteController::class, 'index'])->name('sites.index');
    Route::get('sites/{slug}', [V1\SiteController::class, 'show'])->name('sites.show');
    Route::get('artifacts', [V1\ArtifactController::class, 'index'])->name('artifacts.index');
    Route::get('artifacts/{slug}', [V1\ArtifactController::class, 'show'])->name('artifacts.show');
    Route::get('figures', [V1\FigureController::class, 'index'])->name('figures.index');
    Route::get('figures/{slug}', [V1\FigureController::class, 'show'])->name('figures.show');
    Route::get('geojson/sites', [V1\CatalogController::class, 'geojson'])->name('geojson.sites');
    Route::get('timeline', [V1\CatalogController::class, 'timeline'])->name('timeline');
    Route::get('tags', [V1\CatalogController::class, 'tags'])->name('tags');
    Route::get('search', [V1\CatalogController::class, 'search'])->name('search');
    Route::get('openapi.json', V1\OpenApiController::class)->name('openapi');
    Route::get('ical/routes/{slug}.ics', V1\IcalController::class)->name('ical.route');
    // IPN của cổng thanh toán sandbox (server-to-server, xác minh chữ ký HMAC)
    if (config('disanso.payment.gateway') === 'sandbox') {
        Route::post('payments/sandbox/ipn', V1\PaymentIpnController::class)->name('payments.ipn');
    }
});
