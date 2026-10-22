<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/** Luật chung cho phần nội dung (diễn giải, thẻ, sự kiện) của form di sản/hiện vật/nhân vật. */
trait ValidatesHeritageContent
{
    protected function contentRules(): array
    {
        return [
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'period_id' => ['nullable', 'integer', 'exists:periods,id'],
            'interpretations' => ['required', 'array', 'min:1', 'max:6'],
            'interpretations.*.locale' => ['required', Rule::in(['vi', 'en'])],
            'interpretations.*.depth_level' => ['required', 'integer', 'between:1,3'],
            'interpretations.*.title' => ['required', 'string', 'max:255'],
            'interpretations.*.summary' => ['nullable', 'string', 'max:2000'],
            'interpretations.*.body' => ['required', 'string', 'max:100000'],
            'interpretations.*.sources' => ['nullable'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'events' => ['nullable', 'array', 'max:50'],
            'events.*.id' => ['nullable', 'integer'],
            'events.*.event_type' => ['required', Rule::in(['xay_dung', 'trung_tu', 'cong_nhan', 'su_kien', 'sinh', 'mat', 'phat_hien', 'khac'])],
            'events.*.event_year' => ['required', 'integer', 'between:-3000,2100'],
            'events.*.event_month' => ['nullable', 'integer', 'between:1,12'],
            'events.*.event_day' => ['nullable', 'integer', 'between:1,31'],
            'events.*.end_year' => ['nullable', 'integer', 'between:-3000,2100'],
            'events.*.importance' => ['nullable', 'integer', 'between:1,3'],
            'events.*.title_vi' => ['required', 'string', 'max:255'],
            'events.*.title_en' => ['nullable', 'string', 'max:255'],
            'events.*.description_vi' => ['nullable', 'string', 'max:5000'],
            'events.*.description_en' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Form luôn gửi đủ 6 ô (vi/en × độ sâu 1–3): bỏ ô chưa soạn (title, summary, body rỗng)
     * nhưng giữ nguyên chỉ số để khóa lỗi interpretations.{i}.title khớp đúng ô trên giao diện.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('interpretations');
        if (! is_array($items)) {
            return;
        }
        $blank = fn ($v) => ! is_string($v) || trim($v) === '';
        $this->merge(['interpretations' => array_filter($items, fn ($item) => ! is_array($item)
            || ! ($blank($item['title'] ?? null) && $blank($item['summary'] ?? null) && $blank($item['body'] ?? null)))]);
    }

    /** Ràng buộc: mỗi (locale, depth) chỉ một lần; bắt buộc depth 1 tiếng Việt khi công bố. */
    protected function validateInterpretationSet($validator): void
    {
        $validator->after(function ($v) {
            $items = collect($this->input('interpretations', []));
            $keys = $items->map(fn ($i) => ($i['locale'] ?? '').'-'.($i['depth_level'] ?? ''));
            if ($keys->duplicates()->isNotEmpty()) {
                $v->errors()->add('interpretations', __('validation.distinct', ['attribute' => __('validation.attributes.interpretations')]));
            }
            if ($this->input('status') === 'published' && ! $keys->contains('vi-1')) {
                $message = __('validation.required', ['attribute' => __('validation.attributes.interpretations').' (vi, 1)']);
                $v->errors()->add('interpretations', $message);
                // Ô vi-1 là phần tử 0 theo thứ tự cố định của form quản trị
                $v->errors()->add('interpretations.0.title', $message);
            }
        });
    }

    public function withValidator($validator): void
    {
        $this->validateInterpretationSet($validator);
    }
}
