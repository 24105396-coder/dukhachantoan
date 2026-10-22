"""Fixture DataFrame mô phỏng dữ liệu đã nạp từ MySQL (không cần DB)."""
from __future__ import annotations

import pandas as pd
import pytest

from app.db import build_documents
from app.search import SearchIndex
from app.tags import TagSuggester

ENTITIES = pd.DataFrame([
    {"subject_type": "site", "subject_id": 1, "slug": "hoang-thanh-thang-long"},
    {"subject_type": "site", "subject_id": 2, "slug": "van-mieu-quoc-tu-giam"},
    {"subject_type": "site", "subject_id": 3, "slug": "pho-co-hoi-an"},
    {"subject_type": "site", "subject_id": 4, "slug": "kinh-thanh-hue"},
    {"subject_type": "artifact", "subject_id": 10, "slug": "trong-dong-ngoc-lu"},
    {"subject_type": "figure", "subject_id": 20, "slug": "ly-thai-to"},
])


def _i(stype, sid, locale, depth, title, summary, body):
    return {"subject_type": stype, "subject_id": sid, "locale": locale, "depth": depth,
            "title": title, "summary": summary, "body": body}


INTERPRETATIONS = pd.DataFrame([
    _i("site", 1, "vi", 1, "Hoàng thành Thăng Long", "Trung tâm quyền lực của các triều đại Việt Nam.",
       "Khu di tích Hoàng thành Thăng Long ở Hà Nội được UNESCO ghi danh năm 2010, gồm khu khảo cổ 18 Hoàng Diệu."),
    _i("site", 1, "vi", 2, "Hoàng thành Thăng Long – kiến trúc cung đình", "Các lớp kiến trúc chồng xếp.",
       "Các tầng văn hóa từ thời Đại La, Lý, Trần, Lê chồng lên nhau tại Thăng Long."),
    _i("site", 1, "en", 1, "Imperial Citadel of Thang Long", "Seat of power of Vietnamese dynasties.",
       "The Imperial Citadel of Thang Long in Hanoi was inscribed by UNESCO in 2010."),
    _i("site", 2, "vi", 1, "Văn Miếu – Quốc Tử Giám", "Trường đại học đầu tiên của Việt Nam.",
       "Văn Miếu thờ Khổng Tử, có 82 bia tiến sĩ ghi danh người đỗ đạt các khoa thi."),
    _i("site", 2, "en", 1, "Temple of Literature", "Vietnam's first national university.",
       "The temple honours Confucius and keeps 82 doctoral steles."),
    _i("site", 3, "vi", 1, "Phố cổ Hội An", "Thương cảng cổ bên sông Thu Bồn.",
       "Hội An lưu giữ nhà cổ, chùa Cầu và hội quán của thương nhân Hoa, Nhật."),
    _i("site", 4, "vi", 1, "Kinh thành Huế", "Kinh đô triều Nguyễn.",
       "Kinh thành Huế với Đại Nội, Tử Cấm Thành và hệ thống lăng tẩm của các vua Nguyễn."),
    _i("artifact", 10, "vi", 1, "Trống đồng Ngọc Lũ", "Bảo vật quốc gia thuộc văn hóa Đông Sơn.",
       "Trống đồng Ngọc Lũ được đúc bằng đồng, mặt trống trang trí hình chim Lạc và người giã gạo."),
    _i("figure", 20, "vi", 1, "Lý Thái Tổ", "Vị vua sáng lập triều Lý.",
       "Năm 1010 Lý Công Uẩn ban Chiếu dời đô từ Hoa Lư ra Đại La, đổi tên thành Thăng Long."),
])

TAGS = pd.DataFrame([
    {"tag_id": 1, "slug": "di-san-the-gioi", "locale": "vi", "name": "Di sản thế giới",
     "description": "Được UNESCO ghi danh là di sản văn hóa của nhân loại."},
    {"tag_id": 1, "slug": "di-san-the-gioi", "locale": "en", "name": "World Heritage",
     "description": "Inscribed by UNESCO as heritage of humanity."},
    {"tag_id": 2, "slug": "kinh-do-co", "locale": "vi", "name": "Kinh đô cổ",
     "description": "Các kinh thành và trung tâm quyền lực trong lịch sử Việt Nam."},
    {"tag_id": 3, "slug": "kien-truc-cung-dinh", "locale": "vi", "name": "Kiến trúc cung đình",
     "description": "Cung điện, lăng tẩm và công trình hoàng gia."},
    {"tag_id": 4, "slug": "khao-co-hoc", "locale": "vi", "name": "Khảo cổ học",
     "description": "Di chỉ và hiện vật được phát hiện qua khai quật khảo cổ."},
    {"tag_id": 5, "slug": "nho-giao", "locale": "vi", "name": "Nho giáo",
     "description": "Đạo Khổng, khoa cử và giáo dục truyền thống."},
    {"tag_id": 6, "slug": "do-dong", "locale": "vi", "name": "Đồ đồng",
     "description": "Hiện vật đúc bằng đồng như trống đồng, thạp đồng."},
])

TAGGABLES = pd.DataFrame([
    {"tag_id": 1, "taggable_type": "site", "taggable_id": 1},
    {"tag_id": 2, "taggable_type": "site", "taggable_id": 1},
    {"tag_id": 4, "taggable_type": "site", "taggable_id": 1},
    {"tag_id": 5, "taggable_type": "site", "taggable_id": 2},
    {"tag_id": 2, "taggable_type": "site", "taggable_id": 4},
    {"tag_id": 3, "taggable_type": "site", "taggable_id": 4},
    {"tag_id": 1, "taggable_type": "site", "taggable_id": 3},
    {"tag_id": 6, "taggable_type": "artifact", "taggable_id": 10},
    {"tag_id": 2, "taggable_type": "figure", "taggable_id": 20},
])


@pytest.fixture(scope="session")
def documents() -> pd.DataFrame:
    return build_documents(ENTITIES, INTERPRETATIONS, TAGGABLES, TAGS)


@pytest.fixture(scope="session")
def index(documents) -> SearchIndex:
    return SearchIndex.build(documents)


@pytest.fixture(scope="session")
def suggester(index) -> TagSuggester:
    return TagSuggester.build(index, TAGS)


def _clean_tables() -> dict[str, pd.DataFrame]:
    """Bộ dữ liệu sạch: mọi phép kiểm phải ra 0."""
    return {
        "heritage_sites": pd.DataFrame([
            {"id": 1, "slug": "hoang-thanh-thang-long", "status": "published", "lat": 21.0341, "lng": 105.8372,
             "period_id": 3, "province_id": 1},
            {"id": 2, "slug": "pho-co-hoi-an", "status": "published", "lat": 15.8801, "lng": 108.3380,
             "period_id": 4, "province_id": 2},
        ]),
        "artifacts": pd.DataFrame([
            {"id": 10, "slug": "trong-dong-ngoc-lu", "status": "published", "period_id": 1, "site_id": 1},
        ]),
        "historical_figures": pd.DataFrame([
            {"id": 20, "slug": "ly-thai-to", "status": "published", "period_id": 3, "province_id": 1},
        ]),
        "interpretations": pd.DataFrame([
            {"id": n, "subject_type": t, "subject_id": s, "locale": loc, "depth_level": 1, "title": "x",
             "is_machine_translated": 0, "reviewed_by": None}
            for n, (t, s, loc) in enumerate(
                [(t, s, loc) for t, s in (("site", 1), ("site", 2), ("artifact", 10), ("figure", 20))
                 for loc in ("vi", "en")], start=1)
        ]),
        "media_assets": pd.DataFrame([
            {"id": 1, "subject_type": "site", "subject_id": 1, "media_type": "image", "license": "cc_by_sa",
             "source_name": "Wikimedia Commons", "source_url": "https://commons.wikimedia.org/"},
        ]),
        "periods": pd.DataFrame([
            {"id": 1, "code": "hung_vuong", "start_year": -700, "end_year": -179, "sort_order": 1},
            {"id": 3, "code": "ly", "start_year": 1009, "end_year": 1225, "sort_order": 3},
            {"id": 4, "code": "le_so", "start_year": 1428, "end_year": None, "sort_order": 4},
        ]),
        "timeline_events": pd.DataFrame([
            {"id": 1, "site_id": 1, "figure_id": None, "period_id": 3, "event_year": 1010, "end_year": None},
            {"id": 2, "site_id": 2, "figure_id": None, "period_id": None, "event_year": 1999, "end_year": None},
        ]),
        "tour_routes": pd.DataFrame([{"id": 1, "slug": "ha-noi-ngan-nam", "status": "published"}]),
        "tour_route_stops": pd.DataFrame([{"id": 1, "route_id": 1, "stop_order": 1, "site_id": 1}]),
        "tour_slots": pd.DataFrame([
            {"id": 1, "route_id": 1, "starts_at": "2026-11-01 08:00:00", "capacity": 20, "booked_count": 20},
        ]),
        "provinces": pd.DataFrame([
            {"id": 1, "code": "HN", "name": "Hà Nội", "name_en": "Hanoi", "region": "bac"},
            {"id": 2, "code": "DN", "name": "Đà Nẵng", "name_en": "Da Nang", "region": "trung"},
            {"id": 3, "code": "HCM", "name": "TP. Hồ Chí Minh", "name_en": "Ho Chi Minh City", "region": "nam"},
        ]),
        "period_translations": pd.DataFrame([
            {"period_id": 3, "locale": "vi", "name": "Nhà Lý"},
            {"period_id": 3, "locale": "en", "name": "Ly dynasty"},
        ]),
    }


@pytest.fixture
def clean_tables() -> dict[str, pd.DataFrame]:
    return _clean_tables()
