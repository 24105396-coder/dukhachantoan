<?php

namespace App\Http\Requests\Admin;

use App\Models\Artifact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArtifactRequest extends FormRequest
{
    use ValidatesHeritageContent;

    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $artifact = $this->route('artifact');

        return array_merge($this->contentRules(), [
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('artifacts', 'slug')->ignore($artifact?->id)],
            'site_id' => ['required', 'integer', 'exists:heritage_sites,id'],
            'inventory_code' => ['nullable', 'string', 'max:40', Rule::unique('artifacts', 'inventory_code')->ignore($artifact?->id)],
            'artifact_type' => ['required', Rule::in(Artifact::TYPES)],
            'material' => ['nullable', 'string', 'max:120'],
            'year_from' => ['nullable', 'integer', 'between:-3000,2100'],
            'year_to' => ['nullable', 'integer', 'between:-3000,2100', 'gte:year_from'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'width_cm' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'depth_cm' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'is_national_treasure' => ['boolean'],
            'model3d_url' => ['nullable', 'url:https', 'max:255'],
        ]);
    }
}
