import kiem_dinh_du_lieu as cli


def test_cli_exit_codes(monkeypatch, clean_tables, capsys):
    monkeypatch.setattr(cli, "load_quality_tables", lambda: clean_tables)
    assert cli.main(["--no-color"]) == 0
    assert "Q01_MISSING_DEPTH1_VI" in capsys.readouterr().out

    broken = {**clean_tables, "tour_slots": clean_tables["tour_slots"].assign(booked_count=99)}
    monkeypatch.setattr(cli, "load_quality_tables", lambda: broken)
    assert cli.main(["--json"]) == 1

    def boom():
        raise ConnectionError("x")
    monkeypatch.setattr(cli, "load_quality_tables", boom)
    assert cli.main([]) == 2
