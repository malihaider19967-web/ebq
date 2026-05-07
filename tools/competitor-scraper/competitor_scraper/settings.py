"""
Scraper-only configuration. DB credentials are NOT here — they come from
the Laravel `.env` via `storage.laravel_env.load_db_url()`. This module
covers caps, politeness, and user-agent.
"""

from __future__ import annotations

from pathlib import Path

from pydantic_settings import BaseSettings, SettingsConfigDict


class ScraperSettings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=str(Path(__file__).resolve().parent.parent / ".env"),
        env_file_encoding="utf-8",
        env_prefix="COMPETITOR_SCRAPER_",
        extra="ignore",
    )

    max_total_pages: int = 1000
    max_pages_per_external_domain: int = 5
    max_depth: int = 4
    request_delay_seconds: float = 1.0
    user_agent: str = "EBQ-CompetitorScraper/1.0 (+https://ebq.io/scraper-info)"

    # Hard server-side ceiling. Admin form caps are capped by these so an
    # admin cannot accidentally request a 500k-page crawl.
    ceiling_total_pages: int = 5000
    ceiling_external_per_domain: int = 25
    ceiling_depth: int = 6


settings = ScraperSettings()
