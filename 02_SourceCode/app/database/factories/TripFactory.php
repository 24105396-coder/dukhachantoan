<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfDay()->addDays(fake()->numberBetween(0, 3));

        return [
            'user_id' => fn () => User::factory()->traveler(),
            'partner_id' => null,
            'title' => 'Hành trình '.fake()->city(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(3)->toDateString(),
            'group_size' => fake()->numberBetween(1, 6),
            'nationality' => fake()->randomElement(['VN', 'GB', 'US', 'KR', 'JP', 'FR']),
            'preferred_locale' => fake()->randomElement(['vi', 'en']),
            'contact_name' => fake()->name(),
            'contact_phone' => '09'.fake()->numerify('########'),
            'contact_email' => fake()->safeEmail(),
            'status' => Trip::STATUS_ACTIVE,
        ];
    }

    public function forPartner(User $partner): static
    {
        return $this->state(['partner_id' => $partner->id]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => Trip::STATUS_CANCELLED, 'cancelled_at' => now()]);
    }

    public function draft(): static
    {
        return $this->state(['status' => Trip::STATUS_DRAFT]);
    }
}
