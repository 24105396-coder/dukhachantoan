from app.stats import compute_overview


def test_overview_counts_and_groupings(clean_tables):
    out = compute_overview(clean_tables)
    assert (out["sites"], out["artifacts"], out["figures"]) == (2, 1, 1)
    assert out["interpretations"] == 8 and out["media"] == 1
    assert {p["code"]: p["sites"] for p in out["by_province"]} == {"HN": 1, "DN": 1}  # HCM = 0 bị loại
    ly = next(p for p in out["by_period"] if p["code"] == "ly")
    assert ly["name"] == "Nhà Lý" and ly["name_en"] == "Ly dynasty"
    assert (ly["sites"], ly["artifacts"], ly["figures"]) == (1, 0, 1)


def test_overview_empty_tables():
    out = compute_overview({})
    assert out["sites"] == 0 and out["by_province"] == [] and out["by_period"] == []
