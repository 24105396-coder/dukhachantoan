<?php

namespace App\Http\Requests\Admin;

use App\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Phát thông báo thủ công theo vùng. */
class BroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'zone_codes' => ['required', 'array', 'min:1', 'max:50'],
            'zone_codes.*' => ['string', Rule::exists('zones', 'code')],
            'level' => ['required', 'integer', 'between:1,4'],
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:2000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(Notification::CHANNELS)],
        ];
    }
}
