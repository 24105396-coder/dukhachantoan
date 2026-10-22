<?php

namespace App\Http\Requests\Admin;

use App\Models\Alert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlertTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([Alert::STATUS_ACTIVE, Alert::STATUS_UPDATED, Alert::STATUS_EXPIRED, Alert::STATUS_CANCELLED])]];
    }
}
