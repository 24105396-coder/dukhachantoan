<?php

namespace App\Http\Requests;

use App\Models\Trip;
use App\Models\TripMember;
use App\Support\LocalTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tạo/sửa hành trình: ≥ 1 chặng, chặng không chồng giờ nhau, mọi chặng nằm trong khoảng ngày hành trình.
 * Quyền sửa kiểm bằng TripPolicy ở controller.
 */
class TripRequest extends FormRequest
{
    public const MAX_LEGS = 30;
    public const MAX_MEMBERS = 60;
    public const PHONE_REGEX = '/^\+?[0-9 .\-()]{6,20}$/';

    /** Sửa: kiểm TripPolicy trước khi validate (người không có quyền nhận 403, không thấy lỗi dữ liệu). */
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $this->user() !== null && (! $trip instanceof Trip || $this->user()->can('update', $trip));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'group_size' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('dukhach.trips.max_group_size', 200)],
            'nationality' => ['nullable', 'string', 'max:60'],
            'preferred_locale' => ['nullable', Rule::in(['vi', 'en'])],
            'status' => ['nullable', Rule::in([Trip::STATUS_DRAFT, Trip::STATUS_ACTIVE])],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'legs' => ['required', 'array', 'min:1', 'max:'.self::MAX_LEGS],
            'legs.*.zone_code' => ['required', 'string', Rule::exists('zones', 'code')->where('is_active', true)],
            'legs.*.destination_slug' => ['nullable', 'string', Rule::exists('destinations', 'slug')],
            'legs.*.title' => ['nullable', 'string', 'max:190'],
            'legs.*.starts_at' => ['required', 'date'],
            'legs.*.ends_at' => ['required', 'date'],
            'legs.*.lodging' => ['nullable', 'string', 'max:190'],
            'legs.*.transport' => ['nullable', 'string', 'max:120'],
            'legs.*.note' => ['nullable', 'string', 'max:1000'],
            'legs.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'legs.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'members' => ['nullable', 'array', 'max:'.self::MAX_MEMBERS],
            'members.*.id' => ['nullable', 'integer'],
            'members.*.full_name' => ['required', 'string', 'max:120'],
            'members.*.phone' => ['nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'members.*.nationality' => ['nullable', 'string', 'max:60'],
            'members.*.age_group' => ['nullable', Rule::in(TripMember::AGE_GROUPS)],
            'members.*.special_needs' => ['nullable', 'string', 'max:255'],
            'members.*.is_leader' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateLegTimes($validator)];
    }

    /** Mỗi chặng: kết thúc sau bắt đầu, trong [start_date 00:00, end_date 23:59], không chồng chặng khác. */
    private function validateLegTimes(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        $tripStart = Carbon::parse($this->input('start_date'))->startOfDay();
        $tripEnd = Carbon::parse($this->input('end_date'))->endOfDay();
        $legs = collect($this->input('legs', []))->map(fn ($leg, $i) => [
            'i' => $i, 'start' => LocalTime::parse((string) $leg['starts_at']), 'end' => LocalTime::parse((string) $leg['ends_at']),
        ]);
        foreach ($legs as $leg) {
            if ($leg['end']->lte($leg['start'])) {
                $validator->errors()->add("legs.{$leg['i']}.ends_at", __('messages.trips.leg_end_before_start'));
            } elseif ($leg['start']->lt($tripStart) || $leg['end']->gt($tripEnd)) {
                $validator->errors()->add("legs.{$leg['i']}.starts_at", __('messages.trips.leg_outside_dates'));
            }
        }
        $sorted = $legs->sortBy(fn ($l) => $l['start']->getTimestamp())->values();
        for ($k = 1; $k < $sorted->count(); $k++) {
            if ($sorted[$k]['start']->lt($sorted[$k - 1]['end'])) {
                $validator->errors()->add("legs.{$sorted[$k]['i']}.starts_at", __('messages.trips.leg_overlap', ['other' => $sorted[$k - 1]['i'] + 1]));
            }
        }
    }
}
