<?php

namespace App\Http\Resources;

use App\Models\EmergencyContact;
use Illuminate\Http\Request;

/**
 * @mixin EmergencyContact
 */
class ContactItem extends BaseResource
{
    public const RELATIONS = ['translations', 'province', 'zone'];

    public function toArray(Request $request): array
    {
        $c = $this->resource;
        $t = $c->translate();

        return [
            'id' => $c->id,
            'contact_type' => $c->contact_type,
            'type_label' => __('enums.contact_types.'.$c->contact_type),
            'name' => $t?->name ?? $c->phone,
            'note' => $t?->note,
            'phone' => $c->phone,
            'phone_alt' => $c->phone_alt,
            'address' => $c->address,
            'lat' => $c->lat,
            'lng' => $c->lng,
            'hours' => $c->hours,
            'languages' => $c->languages ?? [],
            'is_verified' => (bool) $c->is_verified,
            'verified_at' => $c->verified_at?->toDateString(),
            'source' => ['name' => $c->source_name, 'url' => $c->source_url],
            'province' => $c->province?->brief(),
            'zone' => $c->zone?->brief(),
            'sort_order' => (int) $c->sort_order,
        ];
    }
}
