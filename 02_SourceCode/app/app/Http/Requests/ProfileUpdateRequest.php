<?php

namespace App\Http\Requests;

use App\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Hồ sơ + tùy chọn thông báo (account.update). Mọi trường "sometimes" để cập nhật từng phần. */
class ProfileUpdateRequest extends FormRequest
{
    public const PHONE_REGEX = '/^\+?[0-9]{9,15}$/';
    public const TIME_REGEX = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'nationality' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'locale' => ['sometimes', 'required', Rule::in(['vi', 'en'])],
            'theme' => ['sometimes', 'required', Rule::in(['light', 'dark'])],
            'organization' => ['sometimes', 'nullable', 'string', 'max:190'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'preferences' => ['sometimes', 'array'],
            'preferences.quiet_from' => ['nullable', 'regex:'.self::TIME_REGEX],
            'preferences.quiet_to' => ['nullable', 'regex:'.self::TIME_REGEX],
            'preferences.digest_daily' => ['sometimes', 'boolean'],
            'preferences.language' => ['sometimes', Rule::in(['vi', 'en'])],
        ], $this->channelRules());
    }

    private function channelRules(): array
    {
        $rules = [];
        foreach ([1, 2, 3, 4] as $level) {
            $rules["preferences.channels_level{$level}"] = ['sometimes', 'array', 'max:3'];
            $rules["preferences.channels_level{$level}.*"] = ['string', Rule::in(Notification::CHANNELS)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('validation.custom.contact_phone.regex'),
            'emergency_contact_phone.regex' => __('validation.custom.contact_phone.regex'),
        ];
    }

    /** Trường hồ sơ (không gồm preferences). */
    public function profileData(): array
    {
        $data = collect($this->validated())->except('preferences')->all();
        if (isset($data['nationality'])) {
            $data['nationality'] = strtoupper($data['nationality']);
        }

        return $data;
    }

    public function preferenceData(): ?array
    {
        return $this->has('preferences') ? (array) ($this->validated()['preferences'] ?? []) : null;
    }
}
