<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gọi dịch vụ Python nội bộ. Mọi lỗi/timeout trả null để tầng trên dùng phương án dự phòng.
 * Trạng thái up/down được nhớ 60 giây để không chờ timeout ở mỗi yêu cầu.
 * Lệnh nền (lấy thời tiết, tính rủi ro) gọi thẳng send() với timeout dài, không qua bộ nhớ "down".
 */
class AnalyticsClient
{
    public const STATUS_KEY = 'analytics:up';

    public function isUp(): bool
    {
        return (bool) Cache::remember(self::STATUS_KEY, $this->ttl(), function () {
            return $this->send('get', '/healthz') !== null;
        });
    }

    /** Trạng thái chi tiết của dịch vụ Python: {status, db, last_fetch} hoặc null khi tắt. */
    public function health(): ?array
    {
        return $this->call('get', '/healthz');
    }

    /** Lấy thời tiết Open-Meteo cho các vùng (rỗng = mọi vùng). Gọi nền nên cho timeout dài hơn. */
    public function refreshWeather(array $zoneCodes = [], float $timeout = 60): ?array
    {
        return $this->send('post', '/weather/refresh', array_filter(['zone_codes' => $zoneCodes]), $timeout);
    }

    /** Tính lại risk_scores mọi vùng: [{zone_code, score, level}]. */
    public function computeRisk(float $timeout = 30): ?array
    {
        return $this->send('post', '/risk/compute', [], $timeout);
    }

    /** Sinh cảnh báo nháp từ ngưỡng thời tiết. */
    public function deriveAlerts(float $timeout = 30): ?array
    {
        return $this->send('post', '/weather/derive-alerts', [], $timeout);
    }

    /** Đối sánh không gian một cảnh báo với các chặng: {matches: [...]}; null → dùng dự phòng PHP. */
    public function match(int $alertId): ?array
    {
        $data = $this->call('post', '/match', ['alert_id' => $alertId]);

        return is_array($data['matches'] ?? null) ? $data : null;
    }

    /** Điểm nào nằm trong hình học: {inside: [id]}; null → dùng App\Support\Geo. */
    public function geoContains(array $geometry, array $points): ?array
    {
        $data = $this->call('post', '/geo/contains', ['geometry' => $geometry, 'points' => $points]);

        return is_array($data['inside'] ?? null) ? $data['inside'] : null;
    }

    public function stats(): ?array
    {
        return $this->call('get', '/stats/overview');
    }

    /** Bỏ qua cuộc gọi nếu vừa biết dịch vụ đang tắt. */
    private function call(string $method, string $path, array $body = []): ?array
    {
        if (Cache::get(self::STATUS_KEY) === false) {
            return null;
        }
        $data = $this->send($method, $path, $body);
        Cache::put(self::STATUS_KEY, $data !== null, $this->ttl());

        return $data;
    }

    private function send(string $method, string $path, array $body = [], ?float $timeout = null): ?array
    {
        try {
            $client = $this->client($timeout);
            $response = $method === 'get' ? $client->get($path, $body) : $client->post($path, $body);

            if (! $response->successful()) {
                Log::error('Analytics service error', ['path' => $path, 'status' => $response->status()]);

                return null;
            }
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (Throwable $e) {
            Log::error('Analytics service unavailable', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function client(?float $timeout = null): PendingRequest
    {
        $timeout ??= (float) config('dukhach.analytics.timeout', 3);

        return Http::baseUrl(rtrim((string) config('dukhach.analytics.url'), '/'))
            ->timeout($timeout)
            ->connectTimeout($timeout)
            ->acceptJson()
            ->withHeaders(['X-Analytics-Token' => (string) config('dukhach.analytics.token')]);
    }

    private function ttl(): int
    {
        return (int) config('dukhach.analytics.status_ttl', 60);
    }
}
