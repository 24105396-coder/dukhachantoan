<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContributionReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected', 'needs_revision'])],
            'review_note' => ['nullable', 'required_unless:status,approved', 'string', 'max:500'],
        ];
    }
}
