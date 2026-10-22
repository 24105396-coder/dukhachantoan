<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Vùng hình vuông ~0.4° quanh tâm ngẫu nhiên trong lãnh thổ Việt Nam.
 *
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    public function definition(): array
    {
        $lat = fake()->randomFloat(4, 9.0, 22.5);
        $lng = fake()->randomFloat(4, 103.0, 109.0);
        $name = 'Vùng '.fake()->unique()->numberBetween(1, 99999);

        return [
            'code' => 'zone-'.fake()->unique()->bothify('????-####'),
            'zone_type' => 'custom',
            'province_id' => fn () => FactoryHelpers::provinceId(),
            'name' => $name,
            'name_en' => $name,
            'geometry' => self::square($lat, $lng, 0.2),
            'centroid_lat' => $lat,
            'centroid_lng' => $lng,
            'radius_km' => null,
            'source_name' => 'Dữ liệu kiểm thử',
            'is_active' => true,
        ];
    }

    /** Polygon vuông cạnh 2×$half độ quanh (lat, lng), tọa độ [lng, lat]. */
    public static function square(float $lat, float $lng, float $half): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng - $half, $lat - $half], [$lng + $half, $lat - $half], [$lng + $half, $lat + $half],
            [$lng - $half, $lat + $half], [$lng - $half, $lat - $half],
        ]]];
    }

    /** Không có polygon, chỉ tâm + bán kính. */
    public function circle(float $radiusKm = 20): static
    {
        return $this->state(['geometry' => null, 'radius_km' => $radiusKm]);
    }

    public function inProvince(string $code): static
    {
        return $this->state(fn () => ['province_id' => FactoryHelpers::provinceId($code)]);
    }
}
