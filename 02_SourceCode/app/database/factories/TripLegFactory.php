<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripLeg;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripLeg>
 */
class TripLegFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addHours(fake()->numberBetween(1, 48));

        return [
            'trip_id' => fn () => Trip::factory(),
            'leg_order' => 1,
            'zone_id' => fn () => Zone::factory(),
            'destination_id' => null,
            'title' => 'Chặng '.fake()->city(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDay(),
            'lodging' => 'Khách sạn mô phỏng',
            'transport' => fake()->randomElement(['xe khách', 'máy bay', 'tàu hỏa', 'ô tô riêng']),
        ];
    }

    public function between($from, $to): static
    {
        return $this->state(['starts_at' => $from, 'ends_at' => $to]);
    }
}
