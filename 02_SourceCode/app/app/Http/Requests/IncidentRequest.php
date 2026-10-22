<?php

namespace App\Http\Requests;

use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Báo sự cố thực địa, ảnh tùy chọn (kiểm MIME thật, ≤ 5 MB). */
class IncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'zone_code' => ['required', 'string', Rule::exists('zones', 'code')],
            'hazard_code' => ['required', 'string', Rule::exists('hazard_types', 'code')],
            'trip_code' => ['nullable', 'string', 'max:20'],
            'title' => ['required', 'string', 'min:5', 'max:190'],
            'description' => ['nullable', 'string', 'max:3000'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'photo' => array_merge(['nullable', 'file', 'max:'.UploadPolicy::INCIDENT_PHOTO_MAX_KB], UploadPolicy::rules('image')),
        ];
    }
}
