<?php

namespace Database\Factories;

use App\Models\Interpretation;
use App\Models\Province;
use Illuminate\Database\Eloquent\Model;

/**
 * Tiện ích dùng chung cho factory: tỉnh mặc định và diễn giải depth 1 vi/en.
 */
final class FactoryHelpers
{
    public static function defaultProvinceId(): int
    {
        return Province::firstOrCreate(
            ['code' => 'HN'],
            ['name' => 'Hà Nội', 'name_en' => 'Hanoi', 'region' => 'bac', 'lat' => 21.0285, 'lng' => 105.8542],
        )->id;
    }

    public static function interpretations(Model $subject, string $viPrefix, string $enPrefix): void
    {
        $word = ucfirst(fake()->word()).' '.fake()->numberBetween(1, 9999);
        foreach (['vi' => $viPrefix, 'en' => $enPrefix] as $locale => $prefix) {
            Interpretation::create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'locale' => $locale,
                'depth_level' => 1,
                'title' => "{$prefix} {$word}",
                'summary' => fake()->sentence(12),
                'body' => fake()->paragraphs(2, true),
                'sources' => 'Nguồn thử nghiệm',
            ]);
        }
    }
}
