<?php

namespace Database\Factories;

use App\Models\Alert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cảnh báo kèm bản dịch vi/en (tạo trong afterCreating).
 *
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_id' => fn () => FactoryHelpers::sourceId(),
            'hazard_type_id' => fn () => FactoryHelpers::hazardTypeId(),
            'severity' => fake()->numberBetween(1, 4),
            'status' => Alert::STATUS_ACTIVE,
            'geometry' => null,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'issued_at' => now()->subHours(2),
            'updated_at_source' => now()->subHour(),
            'source_name' => 'Nguồn kiểm thử',
            'source_url' => 'https://example.test/alert',
            'is_simulated' => true,
            'published_at' => now()->subHour(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Alert $alert) {
            if (! $alert->translations()->exists()) {
                $alert->syncTranslations([
                    'vi' => ['headline' => '[MÔ PHỎNG] Cảnh báo '.$alert->code, 'description' => 'Mô tả kiểm thử', 'instructions' => 'Theo dõi thông tin'],
                    'en' => ['headline' => '[SIMULATION] Alert '.$alert->code, 'description' => 'Test description', 'instructions' => 'Stay informed'],
                ]);
            }
        });
    }

    public function draft(): static
    {
        return $this->state(['status' => Alert::STATUS_DRAFT, 'published_at' => null]);
    }

    public function severity(int $severity): static
    {
        return $this->state(['severity' => $severity]);
    }

    public function expiredAt($when): static
    {
        return $this->state(['ends_at' => $when]);
    }
}
