<?php

namespace Database\Factories;

use App\Models\HeritageSite;
use App\Models\Interpretation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Mặc định depth 2 (vi) vì HeritageSiteFactory đã tạo sẵn depth 1 vi/en.
 *
 * @extends Factory<Interpretation>
 */
class InterpretationFactory extends Factory
{
    protected $model = Interpretation::class;

    public function definition(): array
    {
        return [
            'subject_type' => 'site',
            'subject_id' => HeritageSite::factory(),
            'locale' => 'vi',
            'depth_level' => 2,
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(12),
            'body' => fake()->paragraphs(3, true),
            'sources' => "Nguồn A\nNguồn B",
            'is_machine_translated' => false,
            'version' => 1,
        ];
    }

    public function forSubject(\Illuminate\Database\Eloquent\Model $subject): static
    {
        return $this->state(['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey()]);
    }

    public function depth(int $level, string $locale = 'vi'): static
    {
        return $this->state(['depth_level' => $level, 'locale' => $locale]);
    }
}
