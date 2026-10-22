<?php

namespace App\Http\Requests\Admin;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Booking::TRANSITIONS))],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
