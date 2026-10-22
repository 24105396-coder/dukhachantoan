<?php

namespace App\Http\Resources;

use App\Models\ResponseGuide;
use Illuminate\Http\Request;

/**
 * GuideDetail = GuideCard + các bước trước/trong/sau, nguồn (mảng chuỗi đã escape ở Vue).
 *
 * @mixin ResponseGuide
 */
class GuideDetail extends BaseResource
{
    private bool $withTranslations = false;

    /** Kèm toàn bộ bản dịch vi/en (form quản trị). */
    public function withTranslations(): static
    {
        $this->withTranslations = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $g = $this->resource;
        $t = $g->translate();

        $data = (new GuideCard($g))->toArray($request) + [
            'before_steps' => array_values($t?->before_steps ?? []),
            'during_steps' => array_values($t?->during_steps ?? []),
            'after_steps' => array_values($t?->after_steps ?? []),
            'sources' => array_values($t?->sources ?? []),
            'updated_at' => self::iso($t?->updated_at ?? $g->updated_at),
            'related_contacts_hint' => __('messages.guides.contacts_hint'),
            'is_machine_translated' => (bool) ($t?->is_machine_translated ?? false),
            'locale' => $t?->locale,
        ];

        return $this->withTranslations ? $data + ['translations' => $this->allTranslations($g)] : $data;
    }

    /** Toàn bộ bản dịch cho form quản trị. */
    private function allTranslations(ResponseGuide $g): array
    {
        return $g->translations->mapWithKeys(fn ($t) => [$t->locale => [
            'title' => $t->title,
            'summary' => $t->summary,
            'before_steps' => $t->before_steps ?? [],
            'during_steps' => $t->during_steps ?? [],
            'after_steps' => $t->after_steps ?? [],
            'sources' => $t->sources ?? [],
            'is_machine_translated' => (bool) $t->is_machine_translated,
        ]])->all();
    }
}
