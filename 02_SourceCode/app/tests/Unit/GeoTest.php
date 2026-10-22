<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    private const SQUARE = ['type' => 'Polygon', 'coordinates' => [[[109.0, 12.0], [109.4, 12.0], [109.4, 12.5], [109.0, 12.5], [109.0, 12.0]]]];

    public function test_haversine_hanoi_to_ho_chi_minh_city_is_about_1140_km(): void
    {
        $km = Geo::haversineKm(21.0285, 105.8542, 10.7769, 106.7009);

        $this->assertEqualsWithDelta(1140, $km, 15);
        $this->assertSame(0.0, Geo::haversineKm(12.0, 109.0, 12.0, 109.0));
    }

    public function test_point_in_polygon(): void
    {
        $this->assertTrue(Geo::contains(self::SQUARE, 12.25, 109.2));
        $this->assertFalse(Geo::contains(self::SQUARE, 12.6, 109.2));
        $this->assertFalse(Geo::contains(self::SQUARE, 12.25, 108.9));
    }

    public function test_polygon_hole_is_excluded(): void
    {
        $withHole = self::SQUARE;
        $withHole['coordinates'][] = [[109.1, 12.1], [109.3, 12.1], [109.3, 12.3], [109.1, 12.3], [109.1, 12.1]];

        $this->assertFalse(Geo::contains($withHole, 12.2, 109.2));
        $this->assertTrue(Geo::contains($withHole, 12.45, 109.05));
    }

    public function test_multipolygon_and_circle(): void
    {
        $multi = ['type' => 'MultiPolygon', 'coordinates' => [self::SQUARE['coordinates'], [[[105.0, 20.0], [106.0, 20.0], [106.0, 21.0], [105.0, 21.0], [105.0, 20.0]]]]];
        $this->assertTrue(Geo::contains($multi, 20.5, 105.5));
        $this->assertFalse(Geo::contains($multi, 15.0, 107.0));

        $circle = ['type' => 'Circle', 'center' => ['lat' => 12.25, 'lng' => 109.19], 'radius_km' => 10];
        $this->assertTrue(Geo::contains($circle, 12.30, 109.19));   // ~5.6 km
        $this->assertFalse(Geo::contains($circle, 12.40, 109.19));  // ~16.7 km
        $this->assertTrue(Geo::contains(['type' => 'Circle', 'center' => [109.19, 12.25], 'radius_km' => 10], 12.25, 109.2));
    }

    public function test_unknown_geometry_contains_nothing(): void
    {
        $this->assertFalse(Geo::contains(['type' => 'LineString', 'coordinates' => []], 12.0, 109.0));
    }
}
