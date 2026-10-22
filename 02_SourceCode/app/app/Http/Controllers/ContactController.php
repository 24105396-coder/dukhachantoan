<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContactItem;
use App\Models\EmergencyContact;
use App\Services\LookupService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Danh bạ khẩn cấp (F6): nhóm theo tỉnh + số toàn quốc; lọc theo tỉnh, loại. */
class ContactController extends Controller
{
    public function __invoke(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, ['province', 'type', 'q']);
        $contacts = EmergencyContact::query()->with(ContactItem::RELATIONS)->whereNotNull('province_id')
            ->when($filters['province'], fn ($q, $v) => $q->whereHas('province', fn ($p) => $p->where('code', $v)))
            ->when($filters['type'], fn ($q, $v) => $q->where('contact_type', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('phone', 'like', like_contains($v))
                ->orWhereHas('translations', fn ($t) => $t->where('name', 'like', like_contains($v)))))
            ->join('provinces', 'provinces.id', '=', 'emergency_contacts.province_id')
            ->orderBy('provinces.name')->orderBy('emergency_contacts.sort_order')->select('emergency_contacts.*')->get();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts->groupBy('province_id')->map(fn ($items) => [
                'province' => $items->first()->province?->brief(),
                'contacts' => ContactItem::list($items),
            ])->values()->all(),
            'national' => ContactItem::list(EmergencyContact::query()->with(ContactItem::RELATIONS)->whereNull('province_id')
                ->when($filters['type'], fn ($q, $v) => $q->where('contact_type', $v))->orderBy('sort_order')->get()),
            'filters' => $filters,
            'provinces' => $lookup->provinces(),
            'types' => $this->enumOptions('contact_types', EmergencyContact::TYPES),
        ]);
    }
}
