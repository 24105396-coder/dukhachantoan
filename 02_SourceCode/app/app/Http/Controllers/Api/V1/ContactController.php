<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ContactItem;
use App\Models\EmergencyContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API danh bạ khẩn cấp: ?province=KH trả số của tỉnh + số toàn quốc. */
class ContactController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->applyLocale($request);
        $province = $this->filters($request, ['province', 'type']);
        $contacts = EmergencyContact::query()->with(ContactItem::RELATIONS)
            ->when($province['province'], fn ($q, $v) => $q->where(fn ($w) => $w->whereHas('province', fn ($p) => $p->where('code', $v))->orWhereNull('province_id')))
            ->when($province['type'], fn ($q, $v) => $q->where('contact_type', $v))
            ->orderByRaw('province_id IS NULL DESC')->orderBy('province_id')->orderBy('sort_order')->get();

        return response()->json(['data' => ContactItem::list($contacts)]);
    }
}
