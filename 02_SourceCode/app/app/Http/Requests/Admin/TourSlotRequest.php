<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'manager', 'contributor') ?? false;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'between:30,1440'],
            'capacity' => ['required', 'integer', 'between:1,500'],
            'guide_name' => ['nullable', 'string', 'max:120'],
            'language' => ['required', Rule::in(['vi', 'en'])],
        ];
    }
}
