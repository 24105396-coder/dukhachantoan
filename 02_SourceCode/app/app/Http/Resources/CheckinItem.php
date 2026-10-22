<?php

namespace App\Http\Resources;

use App\Models\SafetyCheckin;
use Illuminate\Http\Request;

/**
 * @mixin SafetyCheckin
 */
class CheckinItem extends BaseResource
{
    public const RELATIONS = ['alert.translations', 'user', 'responder', 'trip'];

    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            'id' => $c->id,
            'status' => $c->status,
            'status_label' => __('enums.checkin_statuses.'.$c->status),
            'lat' => $c->lat,
            'lng' => $c->lng,
            'message' => $c->message,
            'source' => $c->source,
            'checked_at' => self::iso($c->checked_at),
            'alert' => $c->alert ? ['code' => $c->alert->code, 'headline' => $c->alert->headline] : null,
            'user' => $c->user ? ['name' => $c->user->name] : null,
            'trip' => $c->relationLoaded('trip') && $c->trip ? ['code' => $c->trip->code, 'title' => $c->trip->title] : null,
            'response_status' => $c->response_status,
            'response_note' => $c->response_note,
            'responded_at' => self::iso($c->responded_at),
            'responder' => $c->responder ? ['name' => $c->responder->name] : null,
        ];
    }
}
