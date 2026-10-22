<?php

namespace Database\Factories;

use App\Models\TourRoute;
use App\Models\TourSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourSlot>
 */
class TourSlotFactory extends Factory
{
    protected $model = TourSlot::class;

    public function definition(): array
    {
        $start = now()->addDays(3)->setTime(9, 0);

        return [
            'route_id' => TourRoute::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(4),
            'capacity' => 20,
            'booked_count' => 0,
            'guide_name' => fake()->name(),
            'language' => 'vi',
            'status' => 'open',
        ];
    }

    public function startingAt(\DateTimeInterface $start, int $hours = 4): static
    {
        return $this->state(fn () => ['starts_at' => $start, 'ends_at' => \Illuminate\Support\Carbon::instance($start)->addHours($hours)]);
    }

    public function capacity(int $capacity, int $booked = 0): static
    {
        return $this->state(['capacity' => $capacity, 'booked_count' => $booked]);
    }
}
