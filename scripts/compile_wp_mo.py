#!/usr/bin/env python3
"""
Compile WordPress plugin PO files into MO files.

Requires: `pip install polib`
"""

from __future__ import annotations

from pathlib import Path
import sys


def main() -> int:
    try:
        import polib  # type: ignore
    except ImportError:
        print("polib is not installed. Install with: pip install polib")
        return 1

    languages_dir = Path(__file__).resolve().parents[1] / "ebq-seo-wp" / "languages"
    if not languages_dir.exists():
        print(f"Languages directory not found: {languages_dir}")
        return 1

    po_files = sorted(languages_dir.glob("ebq-seo-*.po"))
    if not po_files:
        print("No PO files found.")
        return 1

    compiled = 0
    for po_path in po_files:
        mo_path = po_path.with_suffix(".mo")
        po = polib.pofile(str(po_path))
        po.save_as_mofile(str(mo_path))
        compiled += 1
        print(f"Compiled: {po_path.name} -> {mo_path.name}")

    print(f"Done. Compiled {compiled} MO files.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
