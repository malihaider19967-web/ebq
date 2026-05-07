"""
typer entry. Two commands:

  run --scan-id N        canonical path. Loads competitor_scans row #N
                         from MySQL, runs the crawl, flushes results back.

  crawl <seed-url>       ad-hoc debug. Crawls a domain into a scratch SQLite
                         file without touching MySQL.
"""

from __future__ import annotations

import sys
from pathlib import Path
from typing import Optional

import typer

from .runner import run_scan, run_crawl_only

app = typer.Typer(no_args_is_help=True, add_completion=False)


@app.command()
def run(
    scan_id: int = typer.Option(..., "--scan-id", help="competitor_scans row id"),
) -> None:
    """Canonical run path. Invoked by RunCompetitorScanJob in Laravel."""
    exit_code = run_scan(scan_id)
    sys.exit(exit_code)


@app.command()
def crawl(
    seed: str = typer.Argument(..., help="Seed domain URL, e.g. https://example.com"),
    max_pages: int = typer.Option(50, "--max-pages"),
    output: Path = typer.Option(Path("./out"), "--output"),
) -> None:
    """Ad-hoc debug crawl. Writes a scratch SQLite under <output> and exits."""
    output.mkdir(parents=True, exist_ok=True)
    db_path = run_crawl_only(seed=seed, max_pages=max_pages, output_dir=output)
    typer.echo(f"Crawl complete. Scratch DB: {db_path}")


if __name__ == "__main__":
    app()
