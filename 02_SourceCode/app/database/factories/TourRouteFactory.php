<?php

namespace Database\Factories;

use App\Models\TourRoute;
use App\Models\TourRouteTranslation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourRoute>
 */
class TourRouteFactory extends Factory
{
    protected $model = TourRoute::class;

    public function definition(): array
    {
        return [
            'slug' => 'tuyen-'.fake()->unique()->slug(2),
            'province_id' => fn () => FactoryHelpers::defaultProvinceId(),
            'duration_minutes' => 240,
            'distance_km' => 5,
            'difficulty' => 'de',
            'price_vnd' => 200000,
            'default_capacity' => 20,
            'status' => 'published',
            'created_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (TourRoute $route) {
            $word = ucfirst(fake()->word());
            foreach (['vi' => 'Tuyến', 'en' => 'Route'] as $locale => $prefix) {
                TourRouteTranslation::create([
                    'route_id' => $route->id,
                    'locale' => $locale,
                    'name' => "{$prefix} {$word}",
                    'description' => fake()->sentence(15),
                    'highlights' => "- ".fake()->sentence(4)."\n- ".fake()->sentence(4),
                ]);
            }
        });
    }

    public function ownedBy(User $user): static
    {
        return $this->state(['created_by' => $user->id]);
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }
}
