<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactRequest;
use App\Http\Resources\ContactItem;
use App\Models\EmergencyContact;
use App\Models\Province;
use App\Models\Zone;
use App\Services\AuditLogger;
use App\Services\LookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** CRUD danh bạ khẩn cấp + xác minh (agency chỉ tỉnh mình). */
class ContactController extends Controller
{
    public const PER_PAGE = 30;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, ['province', 'type', 'q', 'verified']);
        $contacts = EmergencyContact::query()->with(ContactItem::RELATIONS)
            ->when($request->user()->scopedProvinceId(), fn ($q, $p) => $q->where('province_id', $p))
            ->when($filters['province'], fn ($q, $v) => $q->whereHas('province', fn ($p) => $p->where('code', $v)))
            ->when($filters['type'], fn ($q, $v) => $q->where('contact_type', $v))
            ->when($filters['verified'] !== null, fn ($q) => $q->where('is_verified', $filters['verified'] === '1'))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('phone', 'like', like_contains($v))
                ->orWhereHas('translations', fn ($t) => $t->where('name', 'like', like_contains($v)))))
            ->orderBy('province_id')->orderBy('sort_order')->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Admin/Contacts/Index', [
            'contacts' => ContactItem::paginate($contacts),
            'filters' => $filters,
            'provinces' => $lookup->provinces(),
            'types' => $this->enumOptions('contact_types', EmergencyContact::TYPES),
        ]);
    }

    public function create(LookupService $lookup): Response
    {
        $this->authorize('create', EmergencyContact::class);

        return Inertia::render('Admin/Contacts/Form', $this->formProps($lookup) + ['contact' => null]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $this->authorize('create', EmergencyContact::class);
        $contact = new EmergencyContact;
        $this->save($contact, $request);
        $this->audit->log('admin.contact.create', $contact);

        return redirect(localized_route('admin.contacts.index'))->with('success', __('messages.admin.created'));
    }

    public function edit(LookupService $lookup, EmergencyContact $contact): Response
    {
        $this->authorize('update', $contact);
        $contact->load(ContactItem::RELATIONS);

        return Inertia::render('Admin/Contacts/Form', $this->formProps($lookup) + [
            'contact' => ContactItem::one($contact) + [
                'translations' => $contact->translations->mapWithKeys(fn ($t) => [$t->locale => ['name' => $t->name, 'note' => $t->note]])->all(),
            ],
        ]);
    }

    public function update(ContactRequest $request, EmergencyContact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);
        $this->save($contact, $request);
        $this->audit->log('admin.contact.update', $contact);

        return redirect(localized_route('admin.contacts.index'))->with('success', __('messages.admin.updated'));
    }

    /** Đánh dấu đã xác minh hôm nay. */
    public function verify(Request $request, EmergencyContact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);
        $contact->forceFill(['is_verified' => true, 'verified_at' => now()->toDateString()])->save();
        $this->audit->log('admin.contact.verify', $contact);

        return back()->with('success', __('messages.admin.updated'));
    }

    public function destroy(EmergencyContact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);
        $this->audit->log('admin.contact.delete', $contact, ['phone' => $contact->phone]);
        $contact->delete();

        return back()->with('success', __('messages.admin.deleted'));
    }

    private function save(EmergencyContact $contact, ContactRequest $request): void
    {
        $data = $request->validated();
        $user = $request->user();
        $provinceId = ! empty($data['province_code']) ? Province::query()->where('code', $data['province_code'])->value('id') : null;
        // Cơ quan tỉnh chỉ tạo/sửa danh bạ của tỉnh mình
        if ($user->scopedProvinceId() && $provinceId !== $user->scopedProvinceId()) {
            abort(403);
        }
        $contact->fill(collect($data)->except(['province_code', 'zone_code', 'translations'])->all() + [
            'province_id' => $provinceId,
            'zone_id' => ! empty($data['zone_code']) ? Zone::query()->where('code', $data['zone_code'])->value('id') : null,
            'sort_order' => $data['sort_order'] ?? 0,
        ])->save();
        $contact->syncTranslations($data['translations']);
    }

    private function formProps(LookupService $lookup): array
    {
        return [
            'provinces' => $lookup->provinces(),
            'zones' => $lookup->zones(),
            'types' => $this->enumOptions('contact_types', EmergencyContact::TYPES),
        ];
    }
}
