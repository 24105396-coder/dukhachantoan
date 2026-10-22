<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Soạn cảnh báo: mức 1..4, ≥ 1 vùng hoặc có geometry, tiêu đề tiếng Việt bắt buộc, nguồn bắt buộc (đề yêu cầu ghi nguồn).
 * geometry nhận GeoJSON Polygon/MultiPolygon hoặc {type: Circle, center: {lat,lng}, radius_km} (chuỗi JSON hoặc mảng).
 */
class AlertRequest extends FormRequest
{
    public const MAX_RADIUS_KM = 500;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    protected function prepareForValidation(): void
    {
        $geometry = $this->input('geometry');
        if (is_string($geometry)) {
            $decoded = trim($geometry) === '' ? null : json_decode($geometry, true);
            $this->merge(['geometry' => $decoded ?? ($geometry === '' ? null : 'invalid')]);
        }
    }

    public function rules(): array
    {
        return [
            'hazard_code' => ['required', 'string', Rule::exists('hazard_types', 'code')],
            'severity' => ['required', 'integer', 'between:1,4'],
            'zone_codes' => ['array', 'required_without:geometry'],
            'zone_codes.*' => ['string', Rule::exists('zones', 'code')],
            'geometry' => ['nullable', 'array'],
            'geometry.type' => ['required_with:geometry', Rule::in(['Polygon', 'MultiPolygon', 'Circle'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'source_name' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'translations' => ['required', 'array'],
            'translations.vi.headline' => ['required', 'string', 'max:255'],
            'translations.vi.description' => ['nullable', 'string', 'max:5000'],
            'translations.vi.instructions' => ['nullable', 'string', 'max:3000'],
            'translations.en.headline' => ['nullable', 'string', 'max:255'],
            'translations.en.description' => ['nullable', 'string', 'max:5000'],
            'translations.en.instructions' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $v) => $this->validateGeometry($v)];
    }

    private function validateGeometry(Validator $validator): void
    {
        $geometry = $this->input('geometry');
        if ($geometry === null || $validator->errors()->has('geometry')) {
            return;
        }
        $valid = match ($geometry['type'] ?? null) {
            'Circle' => isset($geometry['center']) && is_array($geometry['center'])
                && is_numeric($geometry['radius_km'] ?? null)
                && (float) $geometry['radius_km'] > 0 && (float) $geometry['radius_km'] <= self::MAX_RADIUS_KM,
            'Polygon', 'MultiPolygon' => is_array($geometry['coordinates'] ?? null) && $geometry['coordinates'] !== [],
            default => false,
        };
        if (! $valid) {
            $validator->errors()->add('geometry', __('messages.alerts.invalid_geometry'));
        }
    }
}
