<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['required', 'boolean'],
            // Tỉnh phụ trách của cơ quan hỗ trợ; rỗng = toàn quốc
            'province_code' => ['nullable', 'string', 'exists:provinces,code'],
        ];
    }
}
