<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertSource;
use Database\Factories\FactoryHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Lấy thời tiết qua Python, lưu nháp suy diễn chống trùng 24 giờ; Python tắt → giữ dữ liệu cũ, nguồn báo lỗi. */
class WeatherServiceTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    private function draft(string $zone): array
    {
        return [
            'zone_code' => $zone, 'hazard_code' => 'mua_lon', 'severity' => 3,
            'headline' => ['vi' => 'Mưa lớn 120 mm/ngày', 'en' => 'Heavy rain 120 mm/day'],
            'description' => ['vi' => 'Nguy cơ ngập', 'en' => 'Flood risk'], 'instructions' => ['vi' => 'Tránh suối', 'en' => 'Avoid streams'],
            'source' => 'openmeteo-derived', 'source_name' => 'Open-Meteo (suy diễn tự động)', 'source_url' => 'https://open-meteo.com/en/docs',
            'issued_at' => now()->toIso8601String(), 'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addDay()->toIso8601String(),
            'dedupe_key' => "openmeteo-derived:$zone:mua_lon:".now()->toDateString(), 'raw' => ['metric' => 'rain_mm', 'peak' => 120],
        ];
    }

    public function test_fetch_stores_derived_drafts_once_within_24_hours(): void
    {
        $zone = $this->zoneIn('KH', ['code' => 'khanh-hoa']);
        FactoryHelpers::hazardTypeId('mua_lon');
        AlertSource::create(['code' => 'openmeteo', 'name' => 'Open-Meteo', 'source_type' => 'weather', 'provider' => 'openmeteo']);
        Http::fake([
            '*/weather/refresh' => Http::response(['fetched' => 1, 'took_ms' => 120]),
            '*/risk/compute' => Http::response([['zone_code' => 'khanh-hoa', 'score' => 70, 'level' => 3]]),
            '*/weather/derive-alerts' => Http::response(['drafts' => [$this->draft($zone->code), $this->draft('khong-co')], 'count' => 2]),
        ]);

        $this->artisan('app:fetch-weather')->assertSuccessful();
        $this->artisan('app:fetch-weather')->assertSuccessful();

        $alert = Alert::sole();
        $this->assertSame(Alert::STATUS_DRAFT, $alert->status);
        $this->assertSame('openmeteo-derived', $alert->source->code);
        $this->assertSame($zone->id, $alert->zones()->sole()->id);
        $this->assertSame('Heavy rain 120 mm/day', $alert->translate('en')->headline);
        $this->assertSame('ok', AlertSource::where('code', 'openmeteo')->value('last_status'));
    }

    public function test_python_down_keeps_old_data_and_marks_source_error(): void
    {
        AlertSource::create(['code' => 'openmeteo', 'name' => 'Open-Meteo', 'source_type' => 'weather', 'provider' => 'openmeteo', 'last_status' => 'ok']);
        Http::fake(['*' => Http::response(null, 503)]);

        $this->artisan('app:fetch-weather')->assertSuccessful();

        $source = AlertSource::where('code', 'openmeteo')->sole();
        $this->assertSame('error', $source->last_status);
        $this->assertNotNull($source->last_error);
        $this->assertSame(0, Alert::count());
    }
    /** FastAPI trả 422 nếu thân POST là [] (mảng rỗng PHP): client phải gửi đối tượng JSON {}. */
    public function test_python_post_without_params_sends_json_object(): void
    {
        $this->zoneIn('KH', ['code' => 'khanh-hoa']);
        Http::fake(['*' => Http::response(['fetched' => 0, 'drafts' => []])]);

        app(\App\Services\WeatherService::class)->fetchAll();

        foreach (['/weather/refresh', '/risk/compute', '/weather/derive-alerts'] as $path) {
            Http::assertSent(fn ($request) => str_ends_with($request->url(), $path) && $request->body() === '{}'
                && str_contains($request->header('Content-Type')[0] ?? '', 'application/json'));
        }
        Http::fake(['*' => Http::response(['fetched' => 1])]);
        app(\App\Services\AnalyticsClient::class)->refreshWeather(['khanh-hoa']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/weather/refresh') && $request->body() === '{"zone_codes":["khanh-hoa"]}');
    }
}
