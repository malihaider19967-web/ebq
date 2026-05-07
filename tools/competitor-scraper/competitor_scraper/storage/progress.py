"""
HeartbeatWriter — the bridge between the Scrapy worker and the admin
monitor. Stamps competitor_scans.{progress, page_count, last_heartbeat_at}
on every heartbeat, and reads the same row to detect operator-triggered
cancellation.
"""

from __future__ import annotations

import json
from datetime import datetime, timezone
from typing import Optional

from sqlalchemy import update

from .reflect import get_engine, table


class HeartbeatWriter:
    def __init__(self, scan_id: int) -> None:
        self.scan_id = int(scan_id)

    def mark_running(self) -> None:
        scans = table("competitor_scans")
        with get_engine().begin() as conn:
            conn.execute(
                update(scans)
                .where(scans.c.id == self.scan_id)
                .where(scans.c.status.in_(["queued", "running"]))
                .values(
                    status="running",
                    started_at=_now(),
                    last_heartbeat_at=_now(),
                    updated_at=_now(),
                )
            )

    def flush_progress(self, *, current_url: Optional[str], force: bool = False) -> None:
        scans = table("competitor_scans")
        progress = {
            "current_url": current_url,
            "stamp_at": _now().isoformat(),
        }
        with get_engine().begin() as conn:
            conn.execute(
                update(scans)
                .where(scans.c.id == self.scan_id)
                .values(
                    progress=json.dumps(progress),
                    last_heartbeat_at=_now(),
                    updated_at=_now(),
                )
            )

    def is_cancelling(self) -> bool:
        scans = table("competitor_scans")
        with get_engine().connect() as conn:
            row = conn.execute(
                scans.select().with_only_columns(scans.c.status).where(scans.c.id == self.scan_id)
            ).first()
        return bool(row and row[0] == "cancelling")

    def mark_done(self) -> None:
        scans = table("competitor_scans")
        with get_engine().begin() as conn:
            conn.execute(
                update(scans)
                .where(scans.c.id == self.scan_id)
                .where(scans.c.status.in_(["running", "cancelling"]))
                .values(
                    status="done",
                    finished_at=_now(),
                    updated_at=_now(),
                )
            )

    def mark_failed(self, message: str) -> None:
        scans = table("competitor_scans")
        with get_engine().begin() as conn:
            conn.execute(
                update(scans)
                .where(scans.c.id == self.scan_id)
                .values(
                    status="failed",
                    finished_at=_now(),
                    error=(message or "")[:65535],
                    updated_at=_now(),
                )
            )


def _now() -> datetime:
    return datetime.now(timezone.utc).replace(tzinfo=None)
