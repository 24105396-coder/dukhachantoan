<?php

namespace Database\Factories;

use App\Models\HeritageSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeritageSite>
 */
class HeritageSiteFactory extends Factory
{
    protected $model = HeritageSite::class;

    public function definition(): array
    {
        return [
            'slug' => 'di-tich-'.fake()->unique()->slug(2),
            'site_type' => 'di_tich',
            'province_id' => fn () => FactoryHelpers::defaultProvinceId(),
            'address' => fake()->streetAddress(),
            'lat' => fake()->randomFloat(5, 20.9, 21.1),
            'lng' => fake()->randomFloat(5, 105.7, 105.9),
            'recognition_level' => 'quoc_gia',
            'year_built' => fake()->numberBetween(1000, 1900),
            'visiting_hours' => '08:00–17:00',
            'ticket_price_vnd' => 30000,
            'status' => 'published',
            'view_count' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (HeritageSite $site) => FactoryHelpers::interpretations($site, 'Di tích', 'Site'));
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }
}
