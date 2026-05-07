"""
Locate and parse the Laravel `.env` so the Python tool reads MySQL
credentials from the same source the application does.

Resolution order:
  1. EBQ_LARAVEL_ROOT env var → <root>/.env
  2. Walk up from this file: tools/competitor-scraper/.../laravel_env.py is
     four levels deep, so parents[4] is the project root.
  3. Current working directory's .env (last-resort fallback for unusual
     deploys).

Single source of truth for credentials. If `DB_URL` is set in the
Laravel `.env` it takes precedence over the discrete DB_* fields, which
matches Laravel's own behaviour.
"""

from __future__ import annotations

import os
from pathlib import Path
from typing import Optional

from dotenv import dotenv_values
from sqlalchemy import URL


def laravel_root() -> Path:
    explicit = os.environ.get("EBQ_LARAVEL_ROOT")
    if explicit:
        return Path(explicit).expanduser().resolve()

    here = Path(__file__).resolve()
    candidate = here.parents[4]  # tools/<tool>/<pkg>/<sub>/laravel_env.py
    if (candidate / ".env").exists():
        return candidate

    cwd = Path.cwd().resolve()
    if (cwd / ".env").exists():
        return cwd

    return candidate  # caller will get a friendlier error from load_env_file


def load_env_file() -> dict[str, str]:
    root = laravel_root()
    env_path = root / ".env"
    if not env_path.exists():
        raise FileNotFoundError(
            f"Laravel .env not found at {env_path}. "
            f"Set EBQ_LARAVEL_ROOT to override the search path."
        )
    return {k: v for k, v in dotenv_values(env_path).items() if v is not None}


def build_sqlalchemy_url() -> URL:
    env = load_env_file()

    db_url = env.get("DB_URL")
    if db_url:
        return URL.create("dummy")._replace_engine(db_url) if False else _from_url_string(db_url)

    connection = (env.get("DB_CONNECTION") or "mysql").lower()
    if connection != "mysql":
        raise RuntimeError(
            f"DB_CONNECTION={connection!r} is not supported by competitor-scraper. "
            "Only MySQL is wired in v1."
        )

    return URL.create(
        drivername="mysql+pymysql",
        username=env.get("DB_USERNAME") or "",
        password=env.get("DB_PASSWORD") or "",
        host=env.get("DB_HOST") or "127.0.0.1",
        port=int(env.get("DB_PORT") or 3306),
        database=env.get("DB_DATABASE") or "",
        query={"charset": "utf8mb4"},
    )


def _from_url_string(raw: str) -> URL:
    """Laravel's DB_URL allows mysql:// or mysql+pymysql://; SQLAlchemy
    accepts the latter directly."""
    try:
        return URL.create("dummy") if False else _make_url(raw)
    except Exception as e:
        raise RuntimeError(f"DB_URL is not parseable as a SQLAlchemy URL: {e!s}")


def _make_url(raw: str) -> URL:
    from sqlalchemy.engine.url import make_url
    parsed = make_url(raw)
    if parsed.drivername.startswith("mysql") and "pymysql" not in parsed.drivername:
        parsed = parsed.set(drivername="mysql+pymysql")
    return parsed
