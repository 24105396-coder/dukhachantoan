<?php

namespace App\Http\Requests\Admin;

use App\Models\HeritageSite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteRequest extends FormRequest
{
    use ValidatesHeritageContent;

    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $site = $this->route('site');

        return array_merge($this->contentRules(), [
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('heritage_sites', 'slug')->ignore($site?->id)],
            'site_type' => ['required', Rule::in(HeritageSite::TYPES)],
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:8,24'],
            'lng' => ['required', 'numeric', 'between:102,110'],
            'recognition_level' => ['required', Rule::in(HeritageSite::LEVELS)],
            'year_recognized' => ['nullable', 'integer', 'between:1900,2100'],
            'year_built' => ['nullable', 'integer', 'between:-3000,2100'],
            'visiting_hours' => ['nullable', 'string', 'max:120'],
            'ticket_price_vnd' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'figures' => ['nullable', 'array'],
            'figures.*.figure_id' => ['required', 'integer', 'distinct', 'exists:historical_figures,id'],
            'figures.*.relation_type' => ['required', Rule::in(['sang_lap', 'duoc_tho', 'gan_lien', 'trung_tu', 'sinh_ra', 'an_tang'])],
        ]);
    }
}
