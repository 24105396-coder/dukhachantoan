"""Đối sánh cảnh báo – chặng: polygon, circle, zone (gồm vùng con), destination, thời gian, idempotent."""
from datetime import timedelta

from sqlalchemy import select, update

from app import schema
from tests.conftest import square


def _match(client, alert_id, **kw):
    res = client.post("/match", json={"alert_id": alert_id, **kw})
    assert res.status_code == 200, res.text
    return res.json()


def test_polygon_geometry_matches_leg_point_inside(client, seed):
    ids = seed.base_geo()
    trip = seed.trip()
    leg_in = seed.leg(trip, 1, seed.now, zone_id=ids["z_nt"], lat=12.24, lng=109.19)
    seed.leg(trip, 2, seed.now + timedelta(days=1), zone_id=ids["z_lc"], lat=22.33, lng=103.84)
    aid = seed.alert(geometry=square(12.0, 12.5, 109.0, 109.4))
    body = _match(client, aid)
    assert [(m["leg_id"], m["match_type"], m["distance_km"]) for m in body["matches"]] == [(leg_in, "geometry", 0.0)]
    assert body["created"] == 1


def test_circle_geometry_uses_haversine_distance(client, seed):
    ids = seed.base_geo()
    trip = seed.trip()
    leg = seed.leg(trip, 1, seed.now, zone_id=ids["z_pq"])  # không có lat/lng → dùng tâm vùng
    aid = seed.alert(geometry={"type": "Circle", "center": {"lat": 10.3, "lng": 103.96}, "radius_km": 15})
    m = _match(client, aid)["matches"][0]
    assert m["leg_id"] == leg and m["match_type"] == "geometry" and m["point_source"] == "zone_centroid"
    assert 8.0 < m["distance_km"] < 10.0


def test_circle_outside_radius_does_not_match(client, seed):
    ids = seed.base_geo()
    seed.leg(seed.trip(), 1, seed.now, zone_id=ids["z_pq"])
    aid = seed.alert(geometry={"type": "Circle", "center": [104.5, 10.22], "radius_km": 20})
    assert _match(client, aid)["total"] == 0


def test_zone_alert_on_province_covers_child_zone(client, seed):
    ids = seed.base_geo()
    leg = seed.leg(seed.trip(), 1, seed.now, zone_id=ids["z_nt"])
    aid = seed.alert(zone_ids=[ids["z_kh"]])
    assert [(m["leg_id"], m["match_type"]) for m in _match(client, aid)["matches"]] == [(leg, "zone")]


def test_destination_match_when_leg_zone_differs(client, seed):
    ids = seed.base_geo()
    leg = seed.leg(seed.trip(), 1, seed.now, zone_id=ids["z_lc"], destination_id=ids["d_vinpearl"])
    aid = seed.alert(zone_ids=[ids["z_nt"]])
    assert _match(client, aid)["matches"][0]["match_type"] == "destination"
    assert _match(client, aid)["matches"][0]["leg_id"] == leg


def test_time_window_must_overlap(client, seed):
    ids = seed.base_geo()
    trip = seed.trip()
    seed.leg(trip, 1, seed.now + timedelta(days=3), zone_id=ids["z_nt"])  # sau khi cảnh báo hết hạn
    aid = seed.alert(zone_ids=[ids["z_kh"]], ends_at=seed.now + timedelta(days=2))
    assert _match(client, aid)["total"] == 0


def test_overlap_window_is_intersection(client, seed):
    ids = seed.base_geo()
    seed.leg(seed.trip(), 1, seed.now, hours=48, zone_id=ids["z_nt"])
    aid = seed.alert(zone_ids=[ids["z_nt"]], starts_at=seed.now + timedelta(hours=6),
                     ends_at=seed.now + timedelta(hours=12))
    m = _match(client, aid)["matches"][0]
    assert m["overlap_from"] == (seed.now + timedelta(hours=6)).isoformat()
    assert m["overlap_to"] == (seed.now + timedelta(hours=12)).isoformat()


def test_inactive_trip_and_draft_alert_are_ignored(client, seed):
    ids = seed.base_geo()
    seed.leg(seed.trip(status="cancelled"), 1, seed.now, zone_id=ids["z_nt"])
    assert _match(client, seed.alert(zone_ids=[ids["z_nt"]]))["total"] == 0
    draft = seed.alert(code="AL-0002", status="draft", zone_ids=[ids["z_nt"]])
    assert "skipped" in _match(client, draft)


def test_matching_is_idempotent_and_keeps_status(client, seed):
    ids = seed.base_geo()
    seed.leg(seed.trip(), 1, seed.now, zone_id=ids["z_nt"])
    aid = seed.alert(zone_ids=[ids["z_kh"]])
    assert _match(client, aid)["created"] == 1
    with seed.engine.begin() as conn:
        conn.execute(update(schema.alert_matches).values(status="notified"))
    second = _match(client, aid)
    assert second["created"] == 0 and second["matches"][0]["is_new"] is False
    with seed.engine.connect() as conn:
        rows = conn.execute(select(schema.alert_matches)).all()
    assert len(rows) == 1 and rows[0].status == "notified"


def test_trip_filter_and_unknown_alert(client, seed):
    ids = seed.base_geo()
    t1, t2 = seed.trip(code="TR-A"), seed.trip(code="TR-B")
    seed.leg(t1, 1, seed.now, zone_id=ids["z_nt"])
    seed.leg(t2, 1, seed.now, zone_id=ids["z_nt"])
    aid = seed.alert(zone_ids=[ids["z_nt"]])
    assert {m["trip_id"] for m in _match(client, aid, trip_id=t2)["matches"]} == {t2}
    assert client.post("/match", json={"alert_id": 999}).status_code == 404
