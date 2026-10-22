<?php

namespace App\Http\Requests;

use App\Models\SafetyCheckin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Báo an toàn (du khách đăng nhập hoặc qua liên kết chia sẻ). */
class CheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([SafetyCheckin::STATUS_SAFE, SafetyCheckin::STATUS_NEED_HELP])],
            'message' => ['nullable', 'string', 'max:1000'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'accuracy_m' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'alert_code' => ['nullable', 'string', 'max:40', Rule::exists('alerts', 'code')],
        ];
    }
}
