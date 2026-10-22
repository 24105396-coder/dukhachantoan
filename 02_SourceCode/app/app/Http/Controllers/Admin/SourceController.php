<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlertSource;
use App\Services\AuditLogger;
use App\Services\WeatherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Nguồn dữ liệu cảnh báo: trạng thái lần lấy gần nhất, nút "Lấy ngay" cho Open-Meteo (chỉ admin). */
class SourceController extends Controller
{
    public const FETCHABLE = ['openmeteo', WeatherService::DERIVED_SOURCE];

    public function index(): Response
    {
        return Inertia::render('Admin/Sources/Index', [
            'sources' => AlertSource::query()->withCount('alerts')->orderBy('id')->get()->map(fn (AlertSource $s) => [
                'code' => $s->code,
                'name' => $s->name,
                'source_type' => $s->source_type,
                'provider' => $s->provider,
                'base_url' => $s->base_url,
                'attribution' => $s->attribution,
                'license' => $s->license,
                'poll_interval_min' => $s->poll_interval_min,
                'last_polled_at' => $s->last_polled_at?->toIso8601String(),
                'last_status' => $s->last_status,
                'last_error' => $s->last_error,
                'is_active' => (bool) $s->is_active,
                'alerts_count' => (int) $s->alerts_count,
                'can_fetch' => in_array($s->code, self::FETCHABLE, true),
            ])->all(),
        ]);
    }

    public function fetch(Request $request, WeatherService $weather, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'in:'.implode(',', self::FETCHABLE)]]);
        $result = $weather->fetchAll();
        $audit->log('admin.source.fetch', AlertSource::query()->where('code', $data['code'])->first(), $result);

        return $result['status'] === 'ok'
            ? back()->with('success', __('messages.sources.fetched', ['drafts' => $result['drafts_created']]))
            : back()->with('error', __('messages.sources.fetch_failed'));
    }
}
