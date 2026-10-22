<?php

namespace App\Http\Requests\Admin;

use App\Models\ResponseGuide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Hướng dẫn ứng phó 2 ngôn ngữ; các bước là mảng chuỗi (frontend escape, không HTML). */
class GuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        $guide = $this->route('guide');
        $rules = [
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('response_guides', 'slug')->ignore($guide instanceof ResponseGuide ? $guide->id : null)],
            'hazard_code' => ['required', 'string', Rule::exists('hazard_types', 'code')],
            'min_severity' => ['required', 'integer', 'between:1,4'],
            'audience' => ['required', Rule::in(ResponseGuide::AUDIENCES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', Rule::in(ResponseGuide::STATUSES)],
            'translations' => ['required', 'array'],
            'translations.vi.title' => ['required', 'string', 'max:190'],
            'translations.en.title' => ['nullable', 'string', 'max:190'],
        ];
        foreach (['vi', 'en'] as $locale) {
            $rules["translations.$locale.summary"] = ['nullable', 'string', 'max:2000'];
            $rules["translations.$locale.is_machine_translated"] = ['nullable', 'boolean'];
            foreach (['before_steps', 'during_steps', 'after_steps', 'sources'] as $list) {
                $rules["translations.$locale.$list"] = ['nullable', 'array', 'max:20'];
                // Dòng trống (null sau ConvertEmptyStringsToNull) được bỏ khi lưu
                $rules["translations.$locale.$list.*"] = ['nullable', 'string', 'max:500'];
            }
        }

        return $rules;
    }
}
