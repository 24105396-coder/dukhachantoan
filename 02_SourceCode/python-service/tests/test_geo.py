"""Hình học: haversine, đa giác shapely, vòng tròn, định dạng GeoJSON khác nhau."""
import pytest

from app.geo import Area, Circle, GeometryError, contains_points, haversine_km, parse_geometry
from tests.conftest import square


def test_haversine_hanoi_to_hcmc_is_about_1140_km():
    assert haversine_km(21.0285, 105.8542, 10.7769, 106.7009) == pytest.approx(1138, abs=10)


def test_haversine_same_point_is_zero():
    assert haversine_km(12.0, 109.0, 12.0, 109.0) == 0


def test_polygon_contains_inside_and_rejects_outside():
    area = parse_geometry(square(11.8, 12.9, 108.6, 109.5))
    assert isinstance(area, Area)
    assert area.contains(12.25, 109.19)
    assert not area.contains(16.05, 108.2)


def test_polygon_boundary_point_counts_as_inside():
    assert parse_geometry(square(10, 11, 106, 107)).contains(10.0, 106.5)


def test_polygon_with_hole_excludes_hole():
    outer = square(10, 12, 106, 108)["coordinates"][0]
    hole = square(10.5, 11.5, 106.5, 107.5)["coordinates"][0]
    area = parse_geometry({"type": "Polygon", "coordinates": [outer, hole]})
    assert area.contains(10.2, 106.2)
    assert not area.contains(11.0, 107.0)


def test_multipolygon_and_feature_collection():
    mp = {"type": "MultiPolygon", "coordinates": [square(10, 11, 106, 107)["coordinates"],
                                                  square(20, 21, 105, 106)["coordinates"]]}
    assert parse_geometry(mp).contains(20.5, 105.5)
    fc = {"type": "FeatureCollection", "features": [{"type": "Feature", "geometry": square(10, 11, 106, 107)}]}
    assert parse_geometry(fc).contains(10.5, 106.5)


@pytest.mark.parametrize("center", [{"lat": 10.22, "lng": 103.96}, [103.96, 10.22], [10.22, 103.96]])
def test_circle_accepts_dict_geojson_and_latlng_center(center):
    circle = parse_geometry({"type": "Circle", "center": center, "radius_km": 30})
    assert isinstance(circle, Circle)
    assert circle.lat == pytest.approx(10.22) and circle.lng == pytest.approx(103.96)


def test_circle_radius_boundary_by_haversine():
    circle = Circle(lat=10.0, lng=106.0, radius_km=50)
    north_49 = 10.0 + 49 / 111.2
    north_51 = 10.0 + 51 / 111.2
    assert circle.contains(north_49, 106.0)
    assert not circle.contains(north_51, 106.0)


def test_point_feature_with_radius_is_circle():
    feat = {"type": "Feature", "geometry": {"type": "Point", "coordinates": [109.19, 12.24]},
            "properties": {"radius_km": 5}}
    assert parse_geometry(feat).contains(12.25, 109.2)


@pytest.mark.parametrize("bad", [{"type": "LineString", "coordinates": [[0, 0], [1, 1]]},
                                 {"type": "Circle", "center": [109, 12]},
                                 {"type": "Circle", "center": [109, 12], "radius_km": -1},
                                 "not-a-dict"])
def test_invalid_geometry_raises(bad):
    with pytest.raises(GeometryError):
        parse_geometry(bad)


def test_contains_points_returns_ids_and_distances():
    inside, dist = contains_points({"center": {"lat": 10, "lng": 106}, "radius_km": 10},
                                   [{"id": "a", "lat": 10.01, "lng": 106}, {"id": "b", "lat": 11, "lng": 106}])
    assert inside == ["a"]
    assert dist["b"] == pytest.approx(111.2, abs=0.5)
