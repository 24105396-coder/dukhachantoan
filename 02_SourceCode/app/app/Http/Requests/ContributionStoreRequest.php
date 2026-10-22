<?php

namespace App\Http\Requests;

use App\Models\Contribution;
use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContributionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canContribute() ?? false;
    }

    public function rules(): array
    {
        return [
            'contribution_type' => ['required', Rule::in(Contribution::TYPES)],
            'locale' => ['required', Rule::in(['vi', 'en'])],
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'body' => ['required', 'string', 'min:20', 'max:20000'],
            'subject_type' => ['nullable', 'required_with:subject_id', Rule::in(['site', 'artifact', 'figure'])],
            'subject_id' => ['nullable', 'required_with:subject_type', 'integer', 'min:1'],
            'attachment' => ['nullable', 'file', ...UploadPolicy::rules('attachment'), 'max:5120'],
        ];
    }
}
