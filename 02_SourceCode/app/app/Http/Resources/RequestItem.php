<?php

namespace App\Http\Resources;

use App\Models\IncidentReport;
use App\Models\Role;
use App\Models\SafetyCheckin;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Một yêu cầu du khách đã gửi (trang "Yêu cầu của tôi" + GET /api/v1/me/requests):
 * IncidentReport → mục sự cố; SafetyCheckin (need_help) → mục cần hỗ trợ. Vai trò người xử lý thay cho tên cá nhân.
 */
class RequestItem extends BaseResource
{
    public const INCIDENT_RELATIONS = ['hazardType.translations', 'zone.province', 'reviewer.role'];
    public const CHECKIN_RELATIONS = ['trip:id,code,title', 'responder.role'];

    public function toArray(Request $request): array
    {
        return $this->resource instanceof IncidentReport
            ? $this->incident($this->resource)
            : $this->checkin($this->resource);
    }

    private function incident(IncidentReport $i): array
    {
        $alert = $i->relationLoaded('derivedAlert') ? $i->getRelation('derivedAlert') : null;

        return [
            'id' => $i->id,
            'title' => $i->title,
            'hazard' => $i->hazardType ? ['code' => $i->hazardType->code, 'name' => $i->hazardType->name] : null,
            'zone' => $i->zone ? ['name' => $i->zone->localizedName()] : null,
            'province' => $i->zone?->province ? ['name' => $i->zone->province->localizedName()] : null,
            'status' => $i->status,
            'submitted_at' => self::iso($i->created_at),
            'reviewed_at' => self::iso($i->reviewed_at),
            'review_note' => $i->review_note,
            'reviewer_role' => self::staffRole($i->reviewer),
            'alert' => $alert ? [
                'id' => $alert->id, 'title' => $alert->headline, 'code' => $alert->code,
                'url' => localized_route('alerts.show', ['alert' => $alert->code]),
            ] : null,
            'is_simulated' => (bool) $i->is_simulated,
        ];
    }

    private function checkin(SafetyCheckin $c): array
    {
        return [
            'id' => $c->id,
            'trip' => $c->trip ? [
                'id' => $c->trip->id, 'title' => $c->trip->title, 'code' => $c->trip->code,
                'url' => localized_route('trips.show', ['trip' => $c->trip->code]),
            ] : null,
            'status' => $c->status,
            'response_status' => $c->response_status,
            'message' => $c->message,
            'checked_at' => self::iso($c->checked_at),
            'responded_at' => self::iso($c->responded_at),
            'response_note' => $c->response_note,
            'responder_role' => self::staffRole($c->responder),
        ];
    }

    /** 'agency'|'admin'|null – không lộ tên cán bộ xử lý cho du khách. */
    private static function staffRole(?User $user): ?string
    {
        $code = $user?->role?->code;

        return in_array($code, [Role::AGENCY, Role::ADMIN], true) ? $code : null;
    }
}
