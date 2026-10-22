"""Xử lý không gian: GeoJSON Polygon/MultiPolygon (shapely) và vòng tròn {center, radius_km} (haversine)."""
from __future__ import annotations

import math
from dataclasses import dataclass

from shapely.geometry import Point, shape
from shapely.geometry.base import BaseGeometry
from shapely.ops import nearest_points, unary_union

EARTH_RADIUS_KM = 6371.0088
POLYGON_TYPES = {"Polygon", "MultiPolygon"}


class GeometryError(ValueError):
    """Hình học không hợp lệ hoặc không hỗ trợ."""


def haversine_km(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dp, dl = p2 - p1, math.radians(lng2 - lng1)
    a = math.sin(dp / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * EARTH_RADIUS_KM * math.asin(min(1.0, math.sqrt(a)))


@dataclass(frozen=True)
class Circle:
    lat: float
    lng: float
    radius_km: float

    def distance_km(self, lat: float, lng: float) -> float:
        return haversine_km(self.lat, self.lng, lat, lng)

    def contains(self, lat: float, lng: float) -> bool:
        return self.distance_km(lat, lng) <= self.radius_km


@dataclass(frozen=True)
class Area:
    """Vùng đa giác; kiểm tra điểm bằng shapely `covers` (tính cả điểm nằm trên biên)."""
    geom: BaseGeometry

    def contains(self, lat: float, lng: float) -> bool:
        return self.geom.covers(Point(lng, lat))

    def distance_km(self, lat: float, lng: float) -> float:
        """0 khi ở trong; ngoài thì khoảng cách haversine tới điểm gần nhất trên biên (xấp xỉ)."""
        if self.contains(lat, lng):
            return 0.0
        nearest = nearest_points(self.geom.boundary, Point(lng, lat))[0]
        return haversine_km(lat, lng, nearest.y, nearest.x)


Shape = Circle | Area


def _center(value) -> tuple[float, float]:
    """Tâm dạng {lat,lng} hoặc [lng,lat] (GeoJSON); tự nhận [lat,lng] khi phần tử đầu ≤ 90 và sau > 90."""
    if isinstance(value, dict):
        return float(value["lat"]), float(value.get("lng", value.get("lon")))
    if isinstance(value, (list, tuple)) and len(value) >= 2:
        a, b = float(value[0]), float(value[1])
        return (a, b) if abs(a) <= 90 < abs(b) else (b, a)
    raise GeometryError("Tâm vòng tròn không hợp lệ")


def _circle(obj: dict) -> Circle:
    radius = obj.get("radius_km", (obj.get("properties") or {}).get("radius_km"))
    if radius is None and obj.get("radius_m") is not None:
        radius = float(obj["radius_m"]) / 1000
    if radius is None or float(radius) <= 0:
        raise GeometryError("Vòng tròn thiếu radius_km > 0")
    center = obj.get("center")
    if center is None and isinstance(obj.get("geometry"), dict):
        center = obj["geometry"].get("coordinates")
    if center is None:
        center = obj.get("coordinates")
    lat, lng = _center(center)
    return Circle(lat=lat, lng=lng, radius_km=float(radius))


def _polygon(obj: dict) -> Area:
    try:
        geom = shape(obj)
    except Exception as exc:
        raise GeometryError(f"GeoJSON không hợp lệ: {exc}") from exc
    if geom.is_empty:
        raise GeometryError("Đa giác rỗng")
    return Area(geom if geom.is_valid else geom.buffer(0))


def parse_geometry(obj) -> Shape:
    """Nhận Polygon, MultiPolygon, Feature, FeatureCollection (hợp các vùng) hoặc Circle."""
    if not isinstance(obj, dict):
        raise GeometryError("Hình học phải là đối tượng JSON")
    kind = obj.get("type")
    if kind == "Feature":
        props = obj.get("properties") or {}
        inner = obj.get("geometry") or {}
        if inner.get("type") == "Point" and props.get("radius_km") is not None:
            return _circle(obj)
        return parse_geometry(inner)
    if kind == "FeatureCollection":
        areas = [parse_geometry(f) for f in obj.get("features") or []]
        polys = [a.geom for a in areas if isinstance(a, Area)]
        if len(polys) != len(areas) or not polys:
            raise GeometryError("FeatureCollection chỉ hỗ trợ các đa giác")
        return Area(unary_union(polys))
    if kind in POLYGON_TYPES:
        return _polygon(obj)
    if kind in ("Circle", None) or "radius_km" in obj:
        return _circle(obj)
    raise GeometryError(f"Kiểu hình học không hỗ trợ: {kind}")


def contains_points(geometry: dict, points: list[dict]) -> tuple[list, dict]:
    """Trả (danh sách id nằm trong, khoảng cách km tới vùng theo id)."""
    area = parse_geometry(geometry)
    inside, distances = [], {}
    for p in points:
        lat, lng = float(p["lat"]), float(p["lng"])
        distances[p["id"]] = round(area.distance_km(lat, lng), 3)
        if area.contains(lat, lng):
            inside.append(p["id"])
    return inside, distances
