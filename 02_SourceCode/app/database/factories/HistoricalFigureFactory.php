<?php

namespace Database\Factories;

use App\Models\HistoricalFigure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoricalFigure>
 */
class HistoricalFigureFactory extends Factory
{
    protected $model = HistoricalFigure::class;

    public function definition(): array
    {
        $birth = fake()->numberBetween(900, 1800);

        return [
            'slug' => 'nhan-vat-'.fake()->unique()->slug(2),
            'figure_type' => 'vua_chua',
            'birth_year' => $birth,
            'death_year' => $birth + fake()->numberBetween(30, 80),
            'province_id' => fn () => FactoryHelpers::defaultProvinceId(),
            'status' => 'published',
            'view_count' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (HistoricalFigure $f) => FactoryHelpers::interpretations($f, 'Nhân vật', 'Figure'));
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }
}
