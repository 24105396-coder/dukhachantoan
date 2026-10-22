<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Quyền theo tuyến (Policy) kiểm tra ở controller; ở đây chỉ yêu cầu vai trò quản lý tuyến. */
class TourRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'manager', 'contributor') ?? false;
    }

    public function rules(): array
    {
        $route = $this->route('route');

        return [
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tour_routes', 'slug')->ignore($route?->id)],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'theme_tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'duration_minutes' => ['required', 'integer', 'between:30,1440'],
            'distance_km' => ['nullable', 'numeric', 'between:0,1000'],
            'difficulty' => ['required', Rule::in(['de', 'vua', 'kho'])],
            'price_vnd' => ['required', 'integer', 'min:0', 'max:100000000'],
            'default_capacity' => ['required', 'integer', 'between:1,500'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'translations' => ['required', 'array'],
            'translations.vi.name' => ['required', 'string', 'max:200'],
            'translations.vi.description' => ['nullable', 'string', 'max:10000'],
            'translations.vi.highlights' => ['nullable', 'string', 'max:5000'],
            'translations.en.name' => ['nullable', 'string', 'max:200'],
            'translations.en.description' => ['nullable', 'string', 'max:10000'],
            'translations.en.highlights' => ['nullable', 'string', 'max:5000'],
            'stops' => ['required', 'array', 'min:1', 'max:20'],
            'stops.*.site_id' => ['required', 'integer', 'exists:heritage_sites,id'],
            'stops.*.stop_order' => ['nullable', 'integer', 'between:1,50'],
            'stops.*.dwell_minutes' => ['nullable', 'integer', 'between:5,600'],
            'stops.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
