<?php

namespace App\Http\Requests\Admin;

use App\Models\SafetyCheckin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckinResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'response_status' => ['required', Rule::in(SafetyCheckin::RESPONSE_STATUSES)],
            'response_note' => ['nullable', 'string', 'max:1000', 'required_if:response_status,closed'],
        ];
    }
}
