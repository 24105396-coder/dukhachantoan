<?php

namespace App\Http\Requests\Admin;

use App\Models\EmergencyContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'province_code' => ['nullable', 'string', Rule::exists('provinces', 'code')],
            'zone_code' => ['nullable', 'string', Rule::exists('zones', 'code')],
            'contact_type' => ['required', Rule::in(EmergencyContact::TYPES)],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9 .\-()]{3,40}$/'],
            'phone_alt' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9 .\-()]{3,40}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'hours' => ['nullable', 'string', 'max:60'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'size:2'],
            'source_name' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'translations' => ['required', 'array'],
            'translations.vi.name' => ['required', 'string', 'max:190'],
            'translations.vi.note' => ['nullable', 'string', 'max:1000'],
            'translations.en.name' => ['nullable', 'string', 'max:190'],
            'translations.en.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
