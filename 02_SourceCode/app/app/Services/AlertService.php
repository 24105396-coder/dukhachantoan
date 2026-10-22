<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Alert;
use App\Models\AlertMatch;
use App\Models\AlertSource;
use App\Models\HazardType;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Vòng đời cảnh báo theo Alert::TRANSITIONS: tạo nháp → phát hành (đối sánh + xếp thông báo) → cập nhật →
 * hết hiệu lực / hủy (hủy thông báo đang xếp hàng, đối sánh → resolved). Mọi thao tác ghi audit_logs.
 */
class AlertService
{
    public function __construct(
        private readonly MatchingService $matching,
        private readonly NotificationService $notifications,
        private readonly RiskService $risk,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Tạo cảnh báo nháp. $data: hazard_code, severity, zone_codes[], geometry?, starts_at, ends_at, source_name, source_url,
     * translations{vi,en}{headline, description, instructions}, source_code? (mặc định manual), raw_payload?, is_simulated?, scenario_id?.
     */
    public function create(array $data, ?User $actor): Alert
    {
        $alert = DB::transaction(function () use ($data, $actor) {
            $alert = Alert::create($this->attributes($data) + [
                'source_id' => $this->sourceId($data['source_code'] ?? 'manual'),
                'status' => Alert::STATUS_DRAFT,
                'issued_at' => now(),
                'updated_at_source' => now(),
                'raw_payload' => $data['raw_payload'] ?? null,
                'is_simulated' => (bool) ($data['is_simulated'] ?? false),
                'scenario_id' => $data['scenario_id'] ?? null,
                'created_by' => $actor?->id,
                'source_name' => __('messages.alerts.default_source'),
            ]);
            $this->syncRelations($alert, $data);

            return $alert;
        });
        $this->audit->log('alert.create', $alert, ['status' => Alert::STATUS_DRAFT, 'severity' => $alert->severity], $actor);

        return $alert;
    }

    /** Sửa cảnh báo; đang hiệu lực thì chuyển active → updated và đối sánh lại. */
    public function update(Alert $alert, array $data, User $actor): Alert
    {
        if (! in_array($alert->status, [Alert::STATUS_DRAFT, ...Alert::LIVE_STATUSES], true)) {
            throw new BusinessRuleException(__('messages.alerts.not_editable'));
        }
        $wasLive = in_array($alert->status, Alert::LIVE_STATUSES, true);
        DB::transaction(function () use ($alert, $data, $wasLive) {
            $alert->fill($this->attributes($data));
            if ($wasLive) {
                $alert->status = Alert::STATUS_UPDATED;
                $alert->updated_at_source = now();
            }
            $alert->save();
            $this->syncRelations($alert, $data);
        });
        $this->audit->log('alert.update', $alert, ['status' => $alert->status, 'severity' => $alert->severity], $actor);
        if ($wasLive) {
            $this->propagate($alert->refresh());
        }

        return $alert;
    }

    /** Phát hành nháp: draft → active, đối sánh hành trình và xếp thông báo. @return array{matches:int, notifications:int} */
    public function publish(Alert $alert, ?User $actor): array
    {
        $this->assertTransition($alert, Alert::STATUS_ACTIVE);
        $alert->forceFill([
            'status' => Alert::STATUS_ACTIVE,
            'published_at' => now(),
            'issued_at' => $alert->issued_at ?? now(),
            'starts_at' => $alert->starts_at ?? now(),
            'updated_at_source' => now(),
        ])->save();
        $result = $this->propagate($alert);
        $this->audit->log('alert.publish', $alert, ['status' => Alert::STATUS_ACTIVE] + $result, $actor);
        Log::channel('nghiepvu')->info('Phát hành cảnh báo', ['alert' => $alert->code, 'severity' => $alert->severity] + $result);

        return $result;
    }

    /** Chuyển trạng thái theo nút trên trang quản trị (active | updated | expired | cancelled). */
    public function transition(Alert $alert, string $status, User $actor): array
    {
        return match ($status) {
            Alert::STATUS_ACTIVE => $alert->status === Alert::STATUS_DRAFT ? $this->publish($alert, $actor) : $this->reactivate($alert, $actor),
            Alert::STATUS_UPDATED => $this->markUpdated($alert, $actor),
            Alert::STATUS_EXPIRED, Alert::STATUS_CANCELLED => $this->close($alert, $status, $actor),
            default => throw new BusinessRuleException(__('messages.alerts.invalid_transition')),
        };
    }

    public function expire(Alert $alert, ?User $actor = null): array
    {
        return $this->close($alert, Alert::STATUS_EXPIRED, $actor);
    }

    public function cancel(Alert $alert, ?User $actor = null): array
    {
        return $this->close($alert, Alert::STATUS_CANCELLED, $actor);
    }

    /** Cảnh báo hiệu lực đã quá ends_at → expired. Trả số cảnh báo đã chuyển. */
    public function expireDue(): int
    {
        $due = Alert::query()->whereIn('status', Alert::LIVE_STATUSES)->whereNotNull('ends_at')->where('ends_at', '<=', now())->get();
        $due->each(fn (Alert $alert) => $this->close($alert, Alert::STATUS_EXPIRED, null));

        return $due->count();
    }

    /** Đối sánh lại và xếp thông báo cho đối sánh mới. @return array{matches:int, notifications:int} */
    public function propagate(Alert $alert): array
    {
        $matches = $this->matching->matchAlert($alert);
        $created = $this->notifications->enqueueMatches($matches);
        $this->risk->forgetSummary();

        return ['matches' => $matches->count(), 'notifications' => $created];
    }

    private function reactivate(Alert $alert, User $actor): array
    {
        $this->assertTransition($alert, Alert::STATUS_ACTIVE);
        $alert->forceFill(['status' => Alert::STATUS_ACTIVE, 'updated_at_source' => now()])->save();
        $result = $this->propagate($alert);
        $this->audit->log('alert.activate', $alert, ['status' => Alert::STATUS_ACTIVE] + $result, $actor);

        return $result;
    }

    private function markUpdated(Alert $alert, User $actor): array
    {
        $this->assertTransition($alert, Alert::STATUS_UPDATED);
        $alert->forceFill(['status' => Alert::STATUS_UPDATED, 'updated_at_source' => now()])->save();
        $result = $this->propagate($alert);
        $this->audit->log('alert.update', $alert, ['status' => Alert::STATUS_UPDATED] + $result, $actor);

        return $result;
    }

    /** Hết hiệu lực / hủy: hủy thông báo đang xếp hàng, đối sánh → resolved, tính lại rủi ro hành trình. */
    private function close(Alert $alert, string $status, ?User $actor): array
    {
        $this->assertTransition($alert, $status);
        $cancelled = DB::transaction(function () use ($alert, $status) {
            $alert->forceFill(['status' => $status, 'expired_at' => now()])->save();
            AlertMatch::query()->where('alert_id', $alert->id)->where('status', '!=', AlertMatch::STATUS_RESOLVED)
                ->update(['status' => AlertMatch::STATUS_RESOLVED, 'updated_at' => now()]);

            return $this->notifications->cancelQueuedForAlert($alert);
        });
        $this->risk->refreshTrips(AlertMatch::query()->where('alert_id', $alert->id)->distinct()->pluck('trip_id'));
        $this->risk->forgetSummary();
        $action = $status === Alert::STATUS_EXPIRED ? 'alert.expire' : 'alert.cancel';
        $this->audit->log($action, $alert, ['status' => $status, 'notifications_cancelled' => $cancelled], $actor);

        return ['matches' => 0, 'notifications' => 0, 'cancelled' => $cancelled];
    }

    private function assertTransition(Alert $alert, string $status): void
    {
        if (! $alert->canTransitionTo($status)) {
            throw new BusinessRuleException(__('messages.alerts.invalid_transition_from', [
                'from' => __('enums.alert_statuses.'.$alert->status), 'to' => __('enums.alert_statuses.'.$status),
            ]));
        }
    }

    /** Cột chung từ dữ liệu form (chỉ khóa có mặt). */
    private function attributes(array $data): array
    {
        $map = [
            'severity' => fn ($v) => max(1, min(4, (int) $v)),
            'geometry' => fn ($v) => is_array($v) && $v !== [] ? $v : null,
            'starts_at' => fn ($v) => $v ?: null,
            'ends_at' => fn ($v) => $v ?: null,
            'source_name' => fn ($v) => $v,
            'source_url' => fn ($v) => $v ?: null,
        ];
        $out = [];
        foreach ($map as $key => $cast) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $cast($data[$key]);
            }
        }
        if (! empty($data['hazard_code'])) {
            $out['hazard_type_id'] = HazardType::query()->where('code', $data['hazard_code'])->valueOrFail('id');
        }
        return array_filter($out, fn ($v, $k) => $k !== 'source_name' || $v, ARRAY_FILTER_USE_BOTH);
    }

    private function syncRelations(Alert $alert, array $data): void
    {
        if (array_key_exists('zone_codes', $data)) {
            $alert->zones()->sync(Zone::query()->whereIn('code', (array) $data['zone_codes'])->pluck('id')->all());
        }
        if (! empty($data['translations']) && is_array($data['translations'])) {
            $alert->syncTranslations(collect($data['translations'])
                ->map(fn ($t) => is_array($t) ? array_intersect_key($t, array_flip(['headline', 'description', 'instructions', 'is_machine_translated'])) : [])
                ->filter(fn ($t) => ! empty($t['headline']))->all());
        }
    }

    private function sourceId(string $code): int
    {
        return AlertSource::query()->where('code', $code)->value('id')
            ?? AlertSource::query()->firstOrCreate(['code' => 'manual'], [
                'name' => 'Cơ quan hỗ trợ du khách (nhập tay)', 'source_type' => 'manual', 'provider' => 'manual',
            ])->id;
    }
}
