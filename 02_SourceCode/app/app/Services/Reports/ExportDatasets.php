<?php

namespace App\Services\Reports;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\EmergencyContact;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\SafetyCheckin;
use App\Models\Trip;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Định nghĩa 8 bộ dữ liệu xuất Excel: truy vấn theo phạm vi, quan hệ nạp trước (không N+1), cột và cách ánh xạ một dòng.
 * Tiêu đề cột dịch qua `exports.columns.<key>`; giá trị ngày giờ giữ dạng Carbon để bộ ghi định dạng dd/mm/yyyy hh:mm.
 */
class ExportDatasets
{
    public const SUMMARY = 'tong-hop';
    public const KEYS = ['tong-hop', 'canh-bao', 'hanh-trinh', 'thong-bao', 'an-toan', 'su-co', 'danh-ba', 'nhat-ky'];
    /** Bộ chỉ quản trị hệ thống được xuất (nhật ký kiểm toán). */
    public const ADMIN_ONLY = ['nhat-ky'];

    private const COLUMNS = [
        'canh-bao' => ['code', 'severity', 'severity_label', 'hazard', 'headline', 'status', 'zones', 'starts_at', 'ends_at',
            'issued_at', 'source_name', 'source_url', 'updated_at_source', 'is_simulated'],
        'hanh-trinh' => ['code', 'title', 'start_date', 'end_date', 'group_size', 'status', 'risk_level', 'legs_count', 'zones',
            'owner', 'partner', 'nationality'],
        'thong-bao' => ['id', 'recipient', 'channel', 'level', 'title', 'status', 'attempts', 'max_attempts', 'next_attempt_at',
            'sent_at', 'read_at', 'last_error', 'alert_code', 'trip_code', 'created_at'],
        'an-toan' => ['id', 'trip_code', 'trip_title', 'checkin_status', 'response_status', 'message', 'source', 'checked_at',
            'alert_code', 'responder', 'responded_at', 'response_note'],
        'su-co' => ['id', 'title', 'hazard', 'zone', 'province', 'review_status', 'review_note', 'reviewer', 'reviewed_at',
            'submitted_at', 'reporter', 'is_simulated'],
        'danh-ba' => ['id', 'province', 'zone', 'contact_type', 'name', 'phone', 'phone_alt', 'address', 'hours', 'languages',
            'is_verified', 'verified_at', 'source_name', 'source_url'],
        'nhat-ky' => ['id', 'created_at', 'user', 'action', 'subject_type', 'subject_id', 'ip', 'meta'],
    ];

    private const RELATIONS = [
        'canh-bao' => ['translations', 'hazardType.translations', 'zones'],
        'hanh-trinh' => ['user:id,name', 'partner:id,name,organization', 'legs.zone'],
        'thong-bao' => ['user:id,name', 'alert:id,code', 'trip:id,code'],
        'an-toan' => ['trip:id,code,title', 'alert:id,code', 'responder:id,name'],
        'su-co' => ['hazardType.translations', 'zone.province', 'reviewer:id,name', 'user:id,name'],
        'danh-ba' => ['translations', 'province', 'zone'],
        'nhat-ky' => ['user:id,name,email'],
    ];

    public function __construct(private readonly ReportQueries $queries)
    {
    }

    /** Bộ dữ liệu người dùng được xuất (agency không có nhật ký). @return list<string> */
    public static function allowedFor(User $user): array
    {
        return array_values(array_filter(self::KEYS, fn ($k) => $user->isAdmin() || ! in_array($k, self::ADMIN_ONLY, true)));
    }

    /** @return list<string> */
    public function columns(string $key): array
    {
        return self::COLUMNS[$key] ?? throw new InvalidArgumentException("Bộ dữ liệu không hợp lệ: {$key}");
    }

    /** Truy vấn đã lọc + sắp xếp + nạp quan hệ của một bộ dữ liệu (không gồm tong-hop). */
    public function query(string $key, ReportScope $scope): Builder
    {
        $query = match ($key) {
            'canh-bao' => $this->queries->alerts($scope, true)->orderByDesc('issued_at')->orderByDesc('id'),
            'hanh-trinh' => $this->queries->trips($scope, true)->withCount('legs')->orderByDesc('start_date')->orderByDesc('id'),
            'thong-bao' => $this->queries->notifications($scope, true)->orderByDesc('id'),
            'an-toan' => $this->queries->checkins($scope, true)->orderByDesc('checked_at')->orderByDesc('id'),
            'su-co' => $this->queries->incidents($scope, true)->orderByDesc('id'),
            'danh-ba' => $this->queries->contacts($scope, true)->orderBy('province_id')->orderBy('sort_order')->orderBy('id'),
            'nhat-ky' => $this->queries->auditLogs($scope, true)->orderByDesc('id'),
            default => throw new InvalidArgumentException("Bộ dữ liệu không hợp lệ: {$key}"),
        };

        return $query->with(self::RELATIONS[$key]);
    }

    /** Một dòng dữ liệu theo đúng thứ tự columns($key). */
    public function row(string $key, Model $model): array
    {
        return match ($key) {
            'canh-bao' => $this->alertRow($model),
            'hanh-trinh' => $this->tripRow($model),
            'thong-bao' => $this->notificationRow($model),
            'an-toan' => $this->checkinRow($model),
            'su-co' => $this->incidentRow($model),
            'danh-ba' => $this->contactRow($model),
            'nhat-ky' => $this->auditRow($model),
        };
    }

    private function alertRow(Alert $a): array
    {
        return [
            $a->code, (int) $a->severity, Alert::severityLabel((int) $a->severity), $a->hazardType?->name,
            $a->translate()?->headline ?? $a->code, __('enums.alert_statuses.'.$a->status),
            $a->zones->map(fn (Zone $z) => $z->localizedName())->implode(', '),
            $a->starts_at, $a->ends_at, $a->issued_at, $a->source_name, $a->source_url,
            $a->updated_at_source ?? $a->updated_at, self::yesNo($a->is_simulated),
        ];
    }

    private function tripRow(Trip $t): array
    {
        return [
            $t->code, $t->title, $t->start_date, $t->end_date, (int) $t->group_size, __('enums.trip_statuses.'.$t->status),
            (int) $t->risk_level_cached, (int) $t->legs_count,
            $t->legs->pluck('zone')->filter()->unique('id')->map(fn (Zone $z) => $z->localizedName())->implode(', '),
            $t->user?->name, $t->partner ? ($t->partner->organization ?: $t->partner->name) : null, $t->nationality,
        ];
    }

    private function notificationRow(Notification $n): array
    {
        return [
            $n->id, $n->user?->name, __('enums.channels.'.$n->channel), (int) $n->level, $n->title,
            __('enums.notification_statuses.'.$n->status), (int) $n->attempts, (int) $n->max_attempts, $n->next_attempt_at,
            $n->sent_at, $n->read_at, $n->last_error, $n->alert?->code, $n->trip?->code, $n->created_at,
        ];
    }

    private function checkinRow(SafetyCheckin $c): array
    {
        return [
            $c->id, $c->trip?->code, $c->trip?->title, __('enums.checkin_statuses.'.$c->status),
            __('enums.response_statuses.'.$c->response_status), $c->message, __('exports.values.sources.'.$c->source),
            $c->checked_at, $c->alert?->code, $c->responder?->name, $c->responded_at, $c->response_note,
        ];
    }

    private function incidentRow(IncidentReport $i): array
    {
        return [
            $i->id, $i->title, $i->hazardType?->name, $i->zone?->localizedName(), $i->zone?->province?->localizedName(),
            __('enums.incident_statuses.'.$i->status), $i->review_note, $i->reviewer?->name, $i->reviewed_at,
            $i->created_at, $i->user?->name, self::yesNo($i->is_simulated),
        ];
    }

    private function contactRow(EmergencyContact $c): array
    {
        return [
            $c->id, $c->province?->localizedName() ?? __('exports.values.national'), $c->zone?->localizedName(),
            __('enums.contact_types.'.$c->contact_type), $c->translate()?->name ?? $c->phone, $c->phone, $c->phone_alt,
            $c->address, $c->hours, implode(', ', (array) ($c->languages ?? [])), self::yesNo($c->is_verified),
            $c->verified_at, $c->source_name, $c->source_url,
        ];
    }

    private function auditRow(AuditLog $l): array
    {
        return [
            $l->id, $l->created_at, $l->user ? $l->user->name.' <'.$l->user->email.'>' : null, $l->action,
            $l->subject_type, $l->subject_id, $l->ip,
            $l->meta ? json_encode($l->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ];
    }

    private static function yesNo(mixed $value): string
    {
        return __($value ? 'exports.values.yes' : 'exports.values.no');
    }
}
