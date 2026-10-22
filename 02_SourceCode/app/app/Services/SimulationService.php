<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Jobs\RunSimulationStep;
use App\Models\Alert;
use App\Models\SimulationRun;
use App\Models\SimulationScenario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chạy kịch bản mô phỏng (thách thức nâng cao, chỉ admin): mỗi bước tạo cảnh báo is_simulated=1 gắn scenario_id,
 * phát hành ngay (đối sánh + xếp thông báo). Bước at_minute > 0: chế độ realtime xếp job trễ đúng số phút,
 * chế độ instant tạo ngay với starts_at lệch at_minute. Kết thúc = hết hiệu lực mọi cảnh báo của lần chạy.
 */
class SimulationService
{
    public const MODE_REALTIME = 'realtime';
    public const MODE_INSTANT = 'instant';

    public function __construct(private readonly AlertService $alerts, private readonly AuditLogger $audit)
    {
    }

    public function run(SimulationScenario $scenario, User $actor, ?string $mode = null): SimulationRun
    {
        $mode = in_array($mode, [self::MODE_REALTIME, self::MODE_INSTANT], true) ? $mode : (string) config('dukhach.simulation.default_mode', self::MODE_REALTIME);
        $steps = array_values($scenario->payload['steps'] ?? []);
        if ($steps === []) {
            throw new BusinessRuleException(__('messages.simulation.no_steps'), 'slug');
        }
        $run = SimulationRun::create([
            'scenario_id' => $scenario->id,
            'started_by' => $actor->id,
            'started_at' => now(),
            'summary' => ['mode' => $mode, 'steps_total' => count($steps), 'steps_done' => 0, 'alert_ids' => [], 'alert_codes' => []],
        ]);
        $scenario->forceFill(['status' => 'running'])->save();
        $this->audit->log('simulation.run', $run, ['scenario' => $scenario->slug, 'mode' => $mode, 'steps' => count($steps)], $actor);
        Log::channel('nghiepvu')->info('Bắt đầu mô phỏng', ['scenario' => $scenario->slug, 'run' => $run->id, 'mode' => $mode]);

        foreach ($steps as $index => $step) {
            $minute = max(0, (int) ($step['at_minute'] ?? 0));
            if ($minute > 0 && $mode === self::MODE_REALTIME) {
                RunSimulationStep::dispatch($run->id, $index)->delay(now()->addMinutes($minute));
            } else {
                $this->runStep($run, $index);
            }
        }

        return $run->refresh();
    }

    /** Tạo + phát hành cảnh báo của bước $index; bỏ qua nếu lần chạy đã kết thúc. */
    public function runStep(SimulationRun $run, int $index): ?Alert
    {
        $run->loadMissing('scenario');
        $scenario = $run->scenario;
        $step = $scenario?->payload['steps'][$index] ?? null;
        if (! $scenario || ! is_array($step) || $run->finished_at !== null) {
            return null;
        }
        $minute = max(0, (int) ($step['at_minute'] ?? 0));
        $startsAt = ($run->summary['mode'] ?? self::MODE_REALTIME) === self::MODE_INSTANT ? now()->addMinutes($minute) : now();
        $alert = $this->alerts->create([
            'source_code' => 'simulation',
            'hazard_code' => $step['hazard_code'] ?? $scenario->hazardType?->code,
            'severity' => $step['severity'] ?? $scenario->severity,
            'zone_codes' => $step['zone_codes'] ?? $scenario->zone_codes ?? [],
            'geometry' => $step['geometry'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(max(1, (int) $scenario->duration_hours)),
            'source_name' => $scenario->payload['source_name'] ?? __('messages.simulation.source_name', ['name' => $scenario->localized('name')]),
            'source_url' => $scenario->payload['source_url'] ?? null,
            'is_simulated' => true,
            'scenario_id' => $scenario->id,
            'raw_payload' => ['simulation_run_id' => $run->id, 'step' => $index, 'at_minute' => $minute],
            'translations' => $this->translations($step),
        ], $run->starter);
        $result = $this->alerts->publish($alert, $run->starter);
        $this->recordStep($run, $alert, $result);

        return $alert;
    }

    /** Kết thúc lần chạy: hết hiệu lực mọi cảnh báo của run (hủy thông báo đang xếp hàng), kịch bản → finished. */
    public function finish(SimulationRun $run, User $actor): SimulationRun
    {
        if ($run->finished_at !== null) {
            throw new BusinessRuleException(__('messages.simulation.already_finished'), 'run');
        }
        $run->forceFill(['finished_at' => now()])->save();
        $expired = 0;
        $alertIds = $run->summary['alert_ids'] ?? [];
        Alert::query()->whereIn('id', $alertIds)->whereIn('status', Alert::LIVE_STATUSES)->get()
            ->each(function (Alert $alert) use ($actor, &$expired) {
                $this->alerts->expire($alert, $actor);
                $expired++;
            });
        $run->scenario?->forceFill(['status' => 'finished'])->save();
        $this->audit->log('simulation.finish', $run, ['alerts_expired' => $expired], $actor);

        return $run->refresh();
    }

    /** Cập nhật bộ đếm của run trong khóa dòng (bước trễ có thể chạy song song ở worker). */
    private function recordStep(SimulationRun $run, Alert $alert, array $result): void
    {
        DB::transaction(function () use ($run, $alert, $result) {
            $locked = SimulationRun::query()->whereKey($run->id)->lockForUpdate()->first();
            $summary = $locked->summary ?? [];
            $summary['steps_done'] = (int) ($summary['steps_done'] ?? 0) + 1;
            $summary['alert_ids'] = array_values(array_merge($summary['alert_ids'] ?? [], [$alert->id]));
            $summary['alert_codes'] = array_values(array_merge($summary['alert_codes'] ?? [], [$alert->code]));
            $locked->forceFill([
                'alerts_created' => $locked->alerts_created + 1,
                'matches_created' => $locked->matches_created + (int) ($result['matches'] ?? 0),
                'notifications_created' => $locked->notifications_created + (int) ($result['notifications'] ?? 0),
                'summary' => $summary,
            ])->save();
        });
        $run->refresh();
    }

    private function translations(array $step): array
    {
        return collect(['vi', 'en'])->mapWithKeys(fn ($locale) => [$locale => [
            'headline' => $this->pick($step['headline'] ?? null, $locale),
            'description' => $this->pick($step['description'] ?? null, $locale),
            'instructions' => $this->pick($step['instructions'] ?? null, $locale),
        ]])->all();
    }

    private function pick(mixed $value, string $locale): ?string
    {
        return is_array($value) ? ($value[$locale] ?? $value['vi'] ?? null) : $value;
    }
}
