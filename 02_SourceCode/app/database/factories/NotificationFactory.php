<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->traveler(),
            'channel' => Notification::CHANNEL_INAPP,
            'level' => fake()->numberBetween(1, 4),
            'locale' => 'vi',
            'title' => 'Thông báo kiểm thử',
            'body' => fake()->sentence(),
            'status' => Notification::STATUS_QUEUED,
            'attempts' => 0,
            'max_attempts' => Notification::DEFAULT_MAX_ATTEMPTS,
            'next_attempt_at' => null,
        ];
    }

    public function channel(string $channel): static
    {
        return $this->state(['channel' => $channel]);
    }

    public function sent(): static
    {
        return $this->state(['status' => Notification::STATUS_SENT, 'attempts' => 1, 'sent_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(['status' => Notification::STATUS_FAILED, 'attempts' => Notification::DEFAULT_MAX_ATTEMPTS, 'last_error' => 'simulated failure']);
    }
}
