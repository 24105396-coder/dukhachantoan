<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contact_phone' => preg_replace('/[\s.\-]/', '', (string) $this->input('contact_phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('disanso.booking.max_quantity', 10)],
            'contact_name' => ['required', 'string', 'min:2', 'max:120'],
            'contact_phone' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'contact_email' => ['required', 'string', 'email', 'max:190'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
