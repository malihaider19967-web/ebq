#!/usr/bin/env python3
"""
Generate locale-specific PO scaffold files from the plugin POT.
"""

from __future__ import annotations

from pathlib import Path


LOCALES = [
    "en_US", "es_ES", "fr_FR", "de_DE", "it_IT", "pt_PT", "pt_BR", "nl_NL",
    "ru_RU", "ja", "ko_KR", "zh_CN", "zh_TW", "ar", "tr_TR", "pl_PL", "sv_SE",
    "da_DK", "fi", "nb_NO", "cs_CZ", "hu_HU", "ro_RO", "uk",
]


def update_header(pot_text: str, locale: str) -> str:
    lines = pot_text.splitlines()
    try:
        header_end = lines.index("")
    except ValueError:
        header_end = len(lines)

    header = lines[:header_end]
    body = lines[header_end:]

    out_header: list[str] = []
    inserted = False
    for line in header:
        if line.startswith('"Language-Team:'):
            out_header.append('"Language-Team: EBQ Localization\\n"')
            continue
        if line.startswith('"Content-Transfer-Encoding:'):
            out_header.append(line)
            out_header.append(f'"Language: {locale}\\n"')
            out_header.append('"X-Generator: EBQ scaffold\\n"')
            inserted = True
            continue
        out_header.append(line)

    if not inserted:
        out_header.append(f'"Language: {locale}\\n"')
        out_header.append('"X-Generator: EBQ scaffold\\n"')

    return "\n".join(out_header + body) + "\n"


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    lang_dir = root / "ebq-seo-wp" / "languages"
    pot_path = lang_dir / "ebq-seo.pot"
    pot_text = pot_path.read_text(encoding="utf-8")

    for locale in LOCALES:
        po_path = lang_dir / f"ebq-seo-{locale}.po"
        po_path.write_text(update_header(pot_text, locale), encoding="utf-8", newline="\n")
        print(f"Wrote {po_path.name}")

    print(f"Done. Generated {len(LOCALES)} PO files.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
