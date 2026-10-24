<?php

namespace App\Http\Requests\Admin;

use App\Services\Reports\ReportScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** Bộ lọc trang Báo cáo và xuất Excel: from/to (Y-m-d), tỉnh (id hoặc mã), bộ lọc phụ của màn index. */
class ReportFilterRequest extends FormRequest
{
    /** Khoảng tối đa cho một báo cáo (ngày). */
    public const MAX_RANGE_DAYS = 1100;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        $extra = array_fill_keys(ReportScope::EXTRA_FILTERS, ['nullable', 'string', 'max:100']);

        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => array_values(array_filter(['nullable', 'date_format:Y-m-d', $this->filled('from') ? 'after_or_equal:from' : null])),
            'province' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
        ] + $extra;
    }

    public function after(): array
    {
        return [function ($validator) {
            $from = $this->dateOrNull('from');
            $to = $this->dateOrNull('to');
            if ($from && $to && $from->diffInDays($to) > self::MAX_RANGE_DAYS) {
                $validator->errors()->add('to', __('reports.validation.range_too_long', ['days' => self::MAX_RANGE_DAYS]));
            }
        }];
    }

    private function dateOrNull(string $key): ?CarbonImmutable
    {
        $value = $this->input($key);
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
    }

    public function attributes(): array
    {
        return ['from' => __('reports.filters.from'), 'to' => __('reports.filters.to'), 'province' => __('reports.filters.province')];
    }

    public function scope(): ReportScope
    {
        return ReportScope::fromInput($this->user(), $this->validated());
    }
}
