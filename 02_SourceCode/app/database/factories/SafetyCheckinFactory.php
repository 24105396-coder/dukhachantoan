<?php

namespace Database\Factories;

use App\Models\SafetyCheckin;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafetyCheckin>
 */
class SafetyCheckinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => fn () => Trip::factory(),
            'user_id' => fn (array $attrs) => Trip::find($attrs['trip_id'])?->user_id,
            'status' => SafetyCheckin::STATUS_SAFE,
            'lat' => fake()->randomFloat(5, 9.0, 22.5),
            'lng' => fake()->randomFloat(5, 103.0, 109.0),
            'message' => fake()->sentence(),
            'locale' => 'vi',
            'source' => 'app',
            'checked_at' => now(),
            'response_status' => 'open',
        ];
    }

    public function needHelp(): static
    {
        return $this->state(['status' => SafetyCheckin::STATUS_NEED_HELP]);
    }
}
