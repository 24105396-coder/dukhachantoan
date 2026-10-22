<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:1000', 'required_if:status,rejected'],
        ];
    }
}
