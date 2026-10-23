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

    public const MIN_RADIUS_KM = 0.1;

    public const MIN_RING_POINTS = 3;

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
            'intent' => ['nullable', Rule::in(['draft', 'publish', 'update'])],
            'hazard_code' => ['required', 'string', Rule::exists('hazard_types', 'code')],
            'severity' => ['required', 'integer', 'between:1,4'],
            'zone_codes' => ['array', 'required_without:geometry'],
            'zone_codes.*' => ['string', Rule::exists('zones', 'code')],
            'geometry' => ['nullable', 'array'],
            'geometry.type' => ['required_with:geometry', Rule::in(['Polygon', 'MultiPolygon', 'Circle'])],
            // Khóa con phải có rule, nếu không validated() (excludeUnvalidatedArrayKeys) sẽ bỏ tâm/bán kính/tọa độ
            'geometry.center' => ['exclude_unless:geometry.type,Circle', 'required', 'array'],
            'geometry.center.lat' => ['exclude_unless:geometry.type,Circle', 'required', 'numeric', 'between:-90,90'],
            'geometry.center.lng' => ['exclude_unless:geometry.type,Circle', 'required', 'numeric', 'between:-180,180'],
            'geometry.radius_km' => ['exclude_unless:geometry.type,Circle', 'required', 'numeric',
                'min:'.self::MIN_RADIUS_KM, 'max:'.self::MAX_RADIUS_KM],
            // Polygon/MultiPolygon: GeoJSON [lng, lat]; cấu trúc vòng kiểm trong after() để giữ nguyên mảng lồng
            'geometry.coordinates' => ['exclude_if:geometry.type,Circle', 'required_with:geometry.type', 'array', 'min:1'],
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
        if (! is_array($geometry) || $validator->errors()->hasAny(['geometry', 'geometry.*'])) {
            return;
        }
        $valid = match ($geometry['type'] ?? null) {
            'Circle' => true,
            'Polygon' => self::validPolygon($geometry['coordinates'] ?? null),
            'MultiPolygon' => is_array($geometry['coordinates'] ?? null) && $geometry['coordinates'] !== []
                && collect($geometry['coordinates'])->every(fn ($polygon) => self::validPolygon($polygon)),
            default => false,
        };
        if (! $valid) {
            $validator->errors()->add('geometry', __('messages.alerts.invalid_geometry'));
        }
    }

    /** Polygon = danh sách vòng; mỗi vòng ≥ 3 điểm [lng, lat] trong miền hợp lệ. */
    private static function validPolygon(mixed $rings): bool
    {
        if (! is_array($rings) || $rings === []) {
            return false;
        }

        return collect($rings)->every(fn ($ring) => is_array($ring) && count($ring) >= self::MIN_RING_POINTS
            && collect($ring)->every(fn ($point) => self::validPosition($point)));
    }

    private static function validPosition(mixed $point): bool
    {
        if (! is_array($point) || ! array_is_list($point) || count($point) < 2
            || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
            return false;
        }

        return abs((float) $point[0]) <= 180 && abs((float) $point[1]) <= 90;
    }
}
