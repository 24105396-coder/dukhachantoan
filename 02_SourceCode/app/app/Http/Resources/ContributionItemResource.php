<?php

namespace App\Http\Resources;

use App\Support\UrlToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ContributionItemResource extends BaseResource
{
    public const ATTACHMENT_URL_TTL = 60;

    public const URL_TOKEN_PURPOSE = 'contribution-attachment';

    public const SUBJECT_ROUTES = [
        'site' => ['sites.show', 'site'],
        'artifact' => ['artifacts.show', 'artifact'],
        'figure' => ['figures.show', 'figure'],
    ];

    public static function eager(): array
    {
        return ['user', 'subject' => fn ($q) => $q->with('interpretations')];
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contribution_type' => $this->contribution_type,
            'locale' => $this->locale,
            'title' => $this->title,
            'body' => $this->body,
            'attachment_url' => $this->attachment_path ? self::signedAttachmentUrl($this->id) : null,
            'suggested_tags' => collect($this->suggested_tags ?? [])->map(fn ($t) => [
                'slug' => $t['slug'] ?? null,
                'name' => $t['name'] ?? ($t['slug'] ?? null),
                'score' => isset($t['score']) ? round((float) $t['score'], 3) : null,
            ])->values()->all(),
            'status' => $this->status,
            'review_note' => $this->review_note,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => ['name' => $this->user?->name],
            'subject' => $this->subjectData(),
            'url' => localized_route('contributions.show', ['contribution' => $this->id]),
        ];
    }

    /**
     * URL tải tệp đính kèm: id mã hóa + chữ ký, hết hạn sau ATTACHMENT_URL_TTL phút (mã hóa tham số URL, Mục 2.2).
     * Ký tương đối (không gồm host) để khớp middleware signed:relative khi chạy sau proxy hoặc đổi cổng.
     */
    public static function signedAttachmentUrl(int $id): string
    {
        $locale = in_array(app()->getLocale(), ['vi', 'en'], true) ? app()->getLocale() : config('app.locale', 'vi');

        return URL::temporarySignedRoute(
            'contributions.attachment',
            now()->addMinutes(self::ATTACHMENT_URL_TTL),
            ['locale' => $locale, 'token' => UrlToken::encode(self::URL_TOKEN_PURPOSE, $id)],
            false,
        );
    }

    private function subjectData(): ?array
    {
        $subject = $this->subject_type ? $this->subject : null;
        if (! $subject || ! isset(self::SUBJECT_ROUTES[$this->subject_type])) {
            return null;
        }
        [$name, $param] = self::SUBJECT_ROUTES[$this->subject_type];

        return [
            'type' => $this->subject_type,
            'id' => $subject->id,
            'slug' => $subject->slug,
            'title' => $subject->title,
            'url' => localized_route($name, [$param => $subject->slug]),
        ];
    }
}
