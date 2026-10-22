<?php

namespace App\Services;

use App\Models\Artifact;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;

/** Dữ liệu có cấu trúc schema.org cho SEO và liên thông dữ liệu mở. */
class JsonLdBuilder
{
    public function __construct(private readonly ContentLocalizer $content)
    {
    }

    public function site(HeritageSite $site): array
    {
        $cover = $site->coverImage();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $site->site_type === 'bao_tang' ? 'Museum' : 'TouristAttraction',
            'name' => $site->title,
            'description' => $this->content->plain($site->summary),
            'url' => localized_route('sites.show', ['site' => $site->slug]),
            'image' => $cover?->url,
            'inLanguage' => app()->getLocale(),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $site->address,
                'addressRegion' => $site->province?->localizedName(),
                'addressCountry' => 'VN',
            ]),
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float) $site->lat, 'longitude' => (float) $site->lng],
            'openingHours' => $site->visiting_hours,
            'isAccessibleForFree' => $site->ticket_price_vnd === 0 ? true : null,
            'sameAs' => $site->website,
        ], fn ($v) => $v !== null && $v !== []);
    }

    public function artifact(Artifact $artifact): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'VisualArtwork',
            'name' => $artifact->title,
            'description' => $this->content->plain($artifact->summary),
            'url' => localized_route('artifacts.show', ['artifact' => $artifact->slug]),
            'image' => $artifact->coverImage()?->url,
            'artMedium' => $artifact->material,
            'identifier' => $artifact->inventory_code,
            'locationCreated' => $artifact->site ? ['@type' => 'Place', 'name' => $artifact->site->title] : null,
        ], fn ($v) => $v !== null);
    }

    public function figure(HistoricalFigure $figure): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $figure->title,
            'description' => $this->content->plain($figure->summary),
            'url' => localized_route('figures.show', ['figure' => $figure->slug]),
            'image' => $figure->coverImage()?->url,
            'birthDate' => $figure->birth_year ? (string) $figure->birth_year : null,
            'deathDate' => $figure->death_year ? (string) $figure->death_year : null,
            'birthPlace' => $figure->province ? ['@type' => 'Place', 'name' => $figure->province->localizedName()] : null,
        ], fn ($v) => $v !== null);
    }
}
