<?php

namespace App\Services\Reports;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\EmergencyContact;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\SafetyCheckin;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;

/**
 * Truy vấn gốc theo phạm vi báo cáo (khoảng ngày + tỉnh + bộ lọc phụ), dùng chung cho trang Báo cáo và tệp Excel
 * để số trên trang khớp số dòng trong tệp. Mọi điều kiện đều tham số hóa.
 */
class ReportQueries
{
    /** Cảnh báo phát hành trong kỳ (issued_at, thiếu thì created_at) có vùng thuộc tỉnh. */
    public function alerts(ReportScope $scope, bool $withExtra = false): Builder
    {
        return Alert::query()
            ->whereRaw('COALESCE(alerts.issued_at, alerts.created_at) BETWEEN ? AND ?', $scope->range())
            ->when($scope->provinceId(), fn ($q, $p) => $q->inProvince($p))
            ->when($withExtra && $scope->extra('status'), fn ($q) => $q->where('status', $scope->extra('status')))
            ->when($withExtra && $scope->extra('severity'), fn ($q) => $q->where('severity', (int) $scope->extra('severity')))
            ->when($withExtra && $scope->extra('hazard'), fn ($q) => $q->whereHas('hazardType', fn ($h) => $h->where('code', $scope->extra('hazard'))));
    }

    /** Cảnh báo đang hiệu lực (ảnh chụp hiện tại, không theo kỳ). */
    public function liveAlerts(ReportScope $scope): Builder
    {
        return Alert::query()->live()->when($scope->provinceId(), fn ($q, $p) => $q->inProvince($p));
    }

    /** Hành trình có khoảng ngày giao với kỳ báo cáo. */
    public function trips(ReportScope $scope, bool $withExtra = false): Builder
    {
        return Trip::query()
            ->whereDate('start_date', '<=', $scope->to->toDateString())
            ->whereDate('end_date', '>=', $scope->from->toDateString())
            ->when($scope->provinceId(), fn ($q, $p) => $q->inProvince($p))
            ->when($withExtra && $scope->extra('status'), fn ($q) => $q->where('status', $scope->extra('status')));
    }

    public function notifications(ReportScope $scope, bool $withExtra = false): Builder
    {
        return Notification::query()
            ->whereBetween('notifications.created_at', $scope->range())
            ->when($scope->provinceId(), fn ($q, $p) => $q->inProvince($p))
            ->when($withExtra && $scope->extra('status'), fn ($q) => $q->where('status', $scope->extra('status')))
            ->when($withExtra && $scope->extra('channel'), fn ($q) => $q->where('channel', $scope->extra('channel')))
            ->when($withExtra && $scope->extra('level'), fn ($q) => $q->where('level', (int) $scope->extra('level')));
    }

    public function checkins(ReportScope $scope, bool $withExtra = false): Builder
    {
        return SafetyCheckin::query()
            ->whereBetween('safety_checkins.checked_at', $scope->range())
            ->when($scope->provinceId(), fn ($q, $p) => $q->inProvince($p))
            ->when($withExtra && $scope->extra('status'), fn ($q) => $q->where('status', $scope->extra('status')))
            ->when($withExtra && $scope->extra('response_status'), fn ($q) => $q->where('response_status', $scope->extra('response_status')));
    }

    /** Sự cố gửi trong kỳ, thuộc vùng của tỉnh. */
    public function incidents(ReportScope $scope, bool $withExtra = false): Builder
    {
        return IncidentReport::query()
            ->whereBetween('incident_reports.created_at', $scope->range())
            ->when($scope->provinceId(), fn ($q, $p) => $q->whereHas('zone', fn ($z) => $z->where('province_id', $p)))
            ->when($withExtra && $scope->extra('status'), fn ($q) => $q->where('status', $scope->extra('status')))
            ->when($withExtra && $scope->extra('hazard'), fn ($q) => $q->whereHas('hazardType', fn ($h) => $h->where('code', $scope->extra('hazard'))));
    }

    /** Danh bạ là dữ liệu tham chiếu: không lọc theo kỳ, chỉ theo tỉnh (giống màn admin danh bạ). */
    public function contacts(ReportScope $scope, bool $withExtra = false): Builder
    {
        return EmergencyContact::query()
            ->when($scope->provinceId(), fn ($q, $p) => $q->where('province_id', $p))
            ->when($withExtra && $scope->extra('type'), fn ($q) => $q->where('contact_type', $scope->extra('type')))
            ->when($withExtra && $scope->extra('verified') !== null, fn ($q) => $q->where('is_verified', $scope->extra('verified') === '1'));
    }

    /** Nhật ký kiểm toán (chỉ admin): theo kỳ, không theo tỉnh. */
    public function auditLogs(ReportScope $scope, bool $withExtra = false): Builder
    {
        return AuditLog::query()
            ->whereBetween('audit_logs.created_at', $scope->range())
            ->when($withExtra && $scope->extra('action'), fn ($q) => $q->where('action', 'like', like_escape($scope->extra('action')).'%'));
    }
}
