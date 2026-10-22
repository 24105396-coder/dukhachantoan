<?php

namespace App\Http\Requests\Admin;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $tag = $this->route('tag');

        return [
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tags', 'slug')->ignore($tag?->id)],
            'tag_group' => ['required', Rule::in(Tag::GROUPS)],
            'parent_id' => ['nullable', 'integer', 'exists:tags,id', Rule::notIn([$tag?->id])],
            'name_vi' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'description_vi' => ['nullable', 'string', 'max:500'],
            'description_en' => ['nullable', 'string', 'max:500'],
        ];
    }
}
