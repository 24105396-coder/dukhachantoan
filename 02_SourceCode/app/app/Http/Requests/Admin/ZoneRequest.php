<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Sửa vùng: tên, hình học GeoJSON (dán chuỗi), bán kính, tâm. */
class ZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    protected function prepareForValidation(): void
    {
        $geometry = $this->input('geometry');
        if (is_string($geometry)) {
            $this->merge(['geometry' => trim($geometry) === '' ? null : (json_decode($geometry, true) ?? 'invalid')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'geometry' => ['nullable', 'array'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:500', 'required_without:geometry'],
            'centroid_lat' => ['required', 'numeric', 'between:-90,90'],
            'centroid_lng' => ['required', 'numeric', 'between:-180,180'],
            'population_note' => ['nullable', 'string', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:190'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $g = $this->input('geometry');
            if (is_array($g) && (! in_array($g['type'] ?? null, ['Polygon', 'MultiPolygon'], true) || empty($g['coordinates']))) {
                $v->errors()->add('geometry', __('messages.alerts.invalid_geometry'));
            }
        }];
    }
}
