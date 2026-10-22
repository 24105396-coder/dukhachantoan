<?php

namespace App\Services\Queries;

use App\Http\Resources\AlertCard;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bộ lọc cảnh báo dùng chung trang công khai, API và quản trị.
 * status: active (mặc định, đang hiệu lực) | expired (hết hiệu lực/đã hủy) | all | một trạng thái cụ thể (chỉ quản trị).
 */
class AlertSearch
{
    public const PUBLIC_STATUSES = ['active', 'expired', 'all'];

    public function query(array $filters, bool $includeDrafts = false): Builder
    {
        $query = Alert::query()->with(AlertCard::RELATIONS);
        $this->applyStatus($query, $filters['status'] ?? 'active', $includeDrafts);

        return $query
            ->when($filters['severity'] ?? null, fn ($q, $v) => $q->where('severity', (int) $v))
            ->when($filters['hazard'] ?? null, fn ($q, $v) => $q->whereHas('hazardType', fn ($h) => $h->where('code', $v)))
            ->when($filters['province'] ?? null, fn ($q, $v) => $q->whereHas('zones.province', fn ($p) => $p->where('code', $v)))
            ->when($filters['zone'] ?? null, fn ($q, $v) => $q->whereHas('zones', fn ($z) => $z->where('code', $v)))
            ->when(($filters['simulated'] ?? null) !== null && ($filters['simulated'] ?? '') !== '', fn ($q) => $q->where('is_simulated', in_array($filters['simulated'], ['1', 1, true, 'true'], true)))
            ->when($filters['q'] ?? null, function ($q, $v) {
                $like = like_contains($v);
                $q->where(fn ($w) => $w->where('code', 'like', $like)
                    ->orWhereHas('translations', fn ($t) => $t->where('headline', 'like', $like)->orWhere('description', 'like', $like)));
            })
            ->mostSevere();
    }

    private function applyStatus(Builder $query, string $status, bool $includeDrafts): void
    {
        match (true) {
            $status === 'active' => $query->live(),
            $status === 'expired' => $query->where(fn ($q) => $q->whereIn('status', [Alert::STATUS_EXPIRED, Alert::STATUS_CANCELLED])
                ->orWhere(fn ($w) => $w->whereIn('status', Alert::LIVE_STATUSES)->whereNotNull('ends_at')->where('ends_at', '<=', now()))),
            $includeDrafts && in_array($status, Alert::STATUSES, true) => $query->where('status', $status),
            default => $query->when(! $includeDrafts, fn ($q) => $q->where('status', '!=', Alert::STATUS_DRAFT)),
        };
    }
}
