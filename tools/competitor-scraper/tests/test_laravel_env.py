"""
laravel_env.py builds a SQLAlchemy URL from a Laravel-style .env. We
don't need a live DB for these — just verify the URL is shaped right.
"""

from __future__ import annotations

import os
from pathlib import Path

import pytest


@pytest.fixture
def laravel_env(tmp_path, monkeypatch) -> Path:
    env = tmp_path / ".env"
    env.write_text(
        "DB_CONNECTION=mysql\n"
        "DB_HOST=db.test\n"
        "DB_PORT=3307\n"
        "DB_DATABASE=ebq_test\n"
        "DB_USERNAME=root\n"
        "DB_PASSWORD=s3cret\n"
    )
    monkeypatch.setenv("EBQ_LARAVEL_ROOT", str(tmp_path))
    # Reset cached engine (`get_engine`) by reloading the module.
    import importlib
    from competitor_scraper.storage import laravel_env as mod
    importlib.reload(mod)
    return tmp_path


def test_builds_url_from_discrete_fields(laravel_env):
    from competitor_scraper.storage.laravel_env import build_sqlalchemy_url

    url = build_sqlalchemy_url()
    assert url.drivername == "mysql+pymysql"
    assert url.host == "db.test"
    assert url.port == 3307
    assert url.database == "ebq_test"
    assert url.username == "root"
    assert url.password == "s3cret"


def test_db_url_takes_precedence(tmp_path, monkeypatch):
    env = tmp_path / ".env"
    env.write_text(
        "DB_CONNECTION=mysql\n"
        "DB_HOST=ignored.test\n"
        "DB_PORT=9999\n"
        "DB_DATABASE=ignored\n"
        "DB_USERNAME=ignored\n"
        "DB_PASSWORD=ignored\n"
        "DB_URL=mysql://override:pw@host.example:3306/winning\n"
    )
    monkeypatch.setenv("EBQ_LARAVEL_ROOT", str(tmp_path))
    import importlib
    from competitor_scraper.storage import laravel_env as mod
    importlib.reload(mod)

    url = mod.build_sqlalchemy_url()
    assert url.host == "host.example"
    assert url.database == "winning"
    assert url.username == "override"
    assert url.drivername.startswith("mysql+pymysql")


def test_missing_env_raises(tmp_path, monkeypatch):
    monkeypatch.setenv("EBQ_LARAVEL_ROOT", str(tmp_path))
    import importlib
    from competitor_scraper.storage import laravel_env as mod
    importlib.reload(mod)

    with pytest.raises(FileNotFoundError):
        mod.build_sqlalchemy_url()
