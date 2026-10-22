<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertSource;
use App\Models\WeatherForecast;
use App\Models\WeatherObservation;
use App\Models\Zone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Thời tiết và rủi ro (F2): gọi Python /weather/refresh → /risk/compute → /weather/derive-alerts,
 * lưu cảnh báo nháp suy diễn (source_code openmeteo-derived, chống trùng theo raw_payload.dedupe_key trong 24 giờ)
 * để cơ quan duyệt. Python tắt: giữ dữ liệu cũ, ghi last_status=error vào alert_sources.
 */
class WeatherService
{
    public const DERIVED_SOURCE = 'openmeteo-derived';
    public const DEDUPE_HOURS = 24;
    public const FORECAST_DAYS = 7;

    public function __construct(
        private readonly AnalyticsClient $analytics,
        private readonly AlertService $alerts,
        private readonly RiskService $risk,
    ) {
    }

    /** @return array{status: string, fetched: mixed, risk_zones: int, drafts_created: int, drafts_skipped: int} */
    public function fetchAll(array $zoneCodes = []): array
    {
        $refresh = $this->analytics->refreshWeather($zoneCodes);
        if ($refresh === null) {
            $this->markSource('openmeteo', 'error', 'Dịch vụ phân tích Python không phản hồi; giữ dữ liệu thời tiết cũ.');

            return ['status' => 'error', 'fetched' => 0, 'risk_zones' => 0, 'drafts_created' => 0, 'drafts_skipped' => 0];
        }
        $risk = $this->analytics->computeRisk();
        $derived = $this->analytics->deriveAlerts();
        [$created, $skipped] = $this->storeDrafts($derived['drafts'] ?? []);
        $this->markSource('openmeteo', 'ok', null);
        $this->markSource(self::DERIVED_SOURCE, $derived === null ? 'error' : 'ok', $derived === null ? 'derive-alerts lỗi' : null);
        $this->risk->forgetSummary();

        return [
            'status' => 'ok',
            'fetched' => $refresh['fetched'] ?? $refresh,
            'risk_zones' => is_array($risk) ? count($risk) : 0,
            'drafts_created' => $created,
            'drafts_skipped' => $skipped,
        ];
    }

    /**
     * Ghi các nháp do Python sinh thành alerts status draft; bỏ qua nháp trùng dedupe_key trong 24 giờ.
     *
     * @return array{0: int, 1: int} [đã tạo, bỏ qua]
     */
    public function storeDrafts(array $drafts): array
    {
        if ($drafts !== []) {
            $this->derivedSource();
        }
        $created = 0;
        $skipped = 0;
        foreach ($drafts as $draft) {
            if (! is_array($draft) || empty($draft['dedupe_key']) || empty($draft['zone_code']) || empty($draft['hazard_code'])) {
                $skipped++;

                continue;
            }
            if ($this->isDuplicate($draft['dedupe_key']) || ! Zone::query()->where('code', $draft['zone_code'])->exists()) {
                $skipped++;

                continue;
            }
            $this->alerts->create([
                'source_code' => self::DERIVED_SOURCE,
                'hazard_code' => $draft['hazard_code'],
                'severity' => $draft['severity'] ?? 2,
                'zone_codes' => [$draft['zone_code']],
                'starts_at' => $draft['starts_at'] ?? null,
                'ends_at' => $draft['ends_at'] ?? null,
                'source_name' => $draft['source_name'] ?? 'Open-Meteo',
                'source_url' => $draft['source_url'] ?? 'https://open-meteo.com',
                'raw_payload' => ['dedupe_key' => $draft['dedupe_key'], 'source' => self::DERIVED_SOURCE] + (array) ($draft['raw'] ?? []),
                'translations' => collect(['vi', 'en'])->mapWithKeys(fn ($l) => [$l => [
                    'headline' => $draft['headline'][$l] ?? null,
                    'description' => $draft['description'][$l] ?? null,
                    'instructions' => $draft['instructions'][$l] ?? null,
                ]])->all(),
            ], null);
            $created++;
        }
        if ($created > 0) {
            Log::channel('nghiepvu')->info('Cảnh báo nháp từ Open-Meteo', ['created' => $created, 'skipped' => $skipped]);
        }

        return [$created, $skipped];
    }

    /** Quan trắc mới nhất của vùng (null khi chưa có dữ liệu). */
    public function now(Zone $zone): ?WeatherObservation
    {
        return WeatherObservation::query()->with('zone')->where('zone_id', $zone->id)->latest('observed_at')->first();
    }

    /** @return Collection<int, WeatherForecast> 7 ngày từ hôm nay */
    public function forecast(Zone $zone): Collection
    {
        return WeatherForecast::query()->where('zone_id', $zone->id)->whereDate('forecast_date', '>=', now()->toDateString())
            ->orderBy('forecast_date')->limit(self::FORECAST_DAYS)->get();
    }

    /** Quan trắc mới nhất của 5 vùng du lịch tiêu biểu (bỏ vùng chưa có dữ liệu). @return Collection<int, WeatherObservation> */
    public function highlights(): Collection
    {
        $codes = (array) config('dukhach.weather.highlight_zones', []);

        return Zone::query()->whereIn('code', $codes)->get()
            ->sortBy(fn (Zone $z) => array_search($z->code, $codes, true))
            ->map(fn (Zone $z) => $this->now($z))->filter()->values();
    }

    /** Nguồn "openmeteo-derived" (tạo lần đầu khi có nháp): cùng giấy phép Open-Meteo, ngưỡng do nhóm đặt. */
    public function derivedSource(): AlertSource
    {
        return AlertSource::query()->firstOrCreate(['code' => self::DERIVED_SOURCE], [
            'name' => 'Open-Meteo (cảnh báo nháp suy diễn theo ngưỡng)',
            'source_type' => 'weather',
            'provider' => 'openmeteo',
            'base_url' => 'https://open-meteo.com',
            'attribution' => 'Weather data by Open-Meteo.com; ngưỡng do nhóm đặt, cơ quan duyệt trước khi phát hành',
            'license' => 'CC BY 4.0',
            'poll_interval_min' => 30,
            'is_active' => true,
        ]);
    }

    private function isDuplicate(string $key): bool
    {
        return Alert::query()->where('raw_payload->dedupe_key', $key)
            ->where('created_at', '>=', now()->subHours(self::DEDUPE_HOURS))->exists();
    }

    private function markSource(string $code, string $status, ?string $error): void
    {
        $source = AlertSource::query()->where('code', $code)->first();
        $source?->forceFill(['last_polled_at' => now(), 'last_status' => $status, 'last_error' => $error])->save();
        if ($status === 'error') {
            Log::channel('nghiepvu')->warning('Lấy dữ liệu nguồn thất bại', ['source' => $code, 'error' => $error]);
        }
    }
}
