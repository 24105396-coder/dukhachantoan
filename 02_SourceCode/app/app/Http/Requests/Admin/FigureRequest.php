<?php

namespace App\Http\Requests\Admin;

use App\Models\HistoricalFigure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FigureRequest extends FormRequest
{
    use ValidatesHeritageContent;

    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $figure = $this->route('figure');

        return array_merge($this->contentRules(), [
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('historical_figures', 'slug')->ignore($figure?->id)],
            'figure_type' => ['required', Rule::in(HistoricalFigure::TYPES)],
            'birth_year' => ['nullable', 'integer', 'between:-3000,2100'],
            'death_year' => ['nullable', 'integer', 'between:-3000,2100', 'gte:birth_year'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        ]);
    }
}
