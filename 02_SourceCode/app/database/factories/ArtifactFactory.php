<?php

namespace Database\Factories;

use App\Models\Artifact;
use App\Models\HeritageSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artifact>
 */
class ArtifactFactory extends Factory
{
    protected $model = Artifact::class;

    public function definition(): array
    {
        return [
            'site_id' => HeritageSite::factory(),
            'slug' => 'hien-vat-'.fake()->unique()->slug(2),
            'inventory_code' => 'TEST-'.fake()->unique()->numerify('#####'),
            'artifact_type' => 'tuong',
            'material' => 'Gỗ',
            'year_from' => 1600,
            'year_to' => 1700,
            'is_national_treasure' => false,
            'status' => 'published',
            'view_count' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Artifact $a) => FactoryHelpers::interpretations($a, 'Hiện vật', 'Artifact'));
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }
}
