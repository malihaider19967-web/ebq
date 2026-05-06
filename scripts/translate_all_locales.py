#!/usr/bin/env python3
"""
Machine-translate WP + platform locales from English source.

Uses deep-translator (Google public endpoint). Prefer small batches / single
strings with timeouts — translate_batch() hangs on large batches.

Requirements:
  pip install polib deep-translator

Optional env:
  EBQ_MT_BATCH_SIZE   default 1 (safe). Try 5–8 if stable on your network.
  EBQ_MT_SLEEP_SEC    default 0.12 delay between requests
  EBQ_MT_TIMEOUT_SEC  default 45 per translation call
"""

from __future__ import annotations

from concurrent.futures import ThreadPoolExecutor, TimeoutError as FuturesTimeout
from pathlib import Path
import json
import os
import re
import time
from typing import Iterable

import polib
from deep_translator import GoogleTranslator


ROOT = Path(__file__).resolve().parents[1]
WP_LANG_DIR = ROOT / "ebq-seo-wp" / "languages"
APP_LANG_DIR = ROOT / "lang"

LOCALES = [
    "en_US", "es_ES", "fr_FR", "de_DE", "it_IT", "pt_PT", "pt_BR", "nl_NL",
    "ru_RU", "ja", "ko_KR", "zh_CN", "zh_TW", "ar", "tr_TR", "pl_PL", "sv_SE",
    "da_DK", "fi", "nb_NO", "cs_CZ", "hu_HU", "ro_RO", "uk",
]

GOOGLE_LANG = {
    "en_US": "en",
    "es_ES": "es",
    "fr_FR": "fr",
    "de_DE": "de",
    "it_IT": "it",
    "pt_PT": "pt",
    "pt_BR": "pt",
    "nl_NL": "nl",
    "ru_RU": "ru",
    "ja": "ja",
    "ko_KR": "ko",
    "zh_CN": "zh-CN",
    "zh_TW": "zh-TW",
    "ar": "ar",
    "tr_TR": "tr",
    "pl_PL": "pl",
    "sv_SE": "sv",
    "da_DK": "da",
    "fi": "fi",
    "nb_NO": "no",
    "cs_CZ": "cs",
    "hu_HU": "hu",
    "ro_RO": "ro",
    "uk": "uk",
}

APP_LOCALE_MAP = {
    "en_US": "en",
    "es_ES": "es",
    "fr_FR": "fr",
    "de_DE": "de",
    "it_IT": "it",
    "pt_PT": "pt",
    "pt_BR": "pt_BR",
    "nl_NL": "nl",
    "ru_RU": "ru",
    "ja": "ja",
    "ko_KR": "ko",
    "zh_CN": "zh_CN",
    "zh_TW": "zh_TW",
    "ar": "ar",
    "tr_TR": "tr",
    "pl_PL": "pl",
    "sv_SE": "sv",
    "da_DK": "da",
    "fi": "fi",
    "nb_NO": "nb",
    "cs_CZ": "cs",
    "hu_HU": "hu",
    "ro_RO": "ro",
    "uk": "uk",
}

TOKEN_PATTERN = re.compile(
    r"(%\d+\$[sd]|%[sd]|<[^>]+>|`[^`]+`|\{\d+\}|\[[A-Z_]+\]|\n)"
)

BATCH_SIZE = max(1, int(os.environ.get("EBQ_MT_BATCH_SIZE", "1")))
SLEEP_SEC = float(os.environ.get("EBQ_MT_SLEEP_SEC", "0.12"))
TIMEOUT_SEC = float(os.environ.get("EBQ_MT_TIMEOUT_SEC", "45"))


def chunks(items: list[str], size: int) -> Iterable[list[str]]:
    for i in range(0, len(items), size):
        yield items[i : i + size]


def protect_tokens(text: str) -> tuple[str, list[str]]:
    tokens: list[str] = []

    def repl(match: re.Match[str]) -> str:
        idx = len(tokens)
        tokens.append(match.group(0))
        return f"[[TOK_{idx}]]"

    return TOKEN_PATTERN.sub(repl, text), tokens


def restore_tokens(text: str, tokens: list[str]) -> str:
    for idx, token in enumerate(tokens):
        text = text.replace(f"[[TOK_{idx}]]", token)
    return text


def _translate_raw(translator: GoogleTranslator, text: str) -> str:
    """Single request — used inside timeout executor."""
    prepared, token_map = protect_tokens(text)
    out = translator.translate(prepared)
    if not isinstance(out, str):
        return text
    return restore_tokens(out, token_map)


def translate_one(
    translator: GoogleTranslator,
    text: str,
    *,
    label: str = "",
) -> str:
    """Translate one string with retries and per-call timeout."""
    if not text:
        return text
    for attempt in range(6):
        try:
            with ThreadPoolExecutor(max_workers=1) as ex:
                fut = ex.submit(_translate_raw, translator, text)
                return fut.result(timeout=TIMEOUT_SEC)
        except FuturesTimeout:
            wait = 3 + attempt * 2
            print(f"  [timeout] {label!s} retry {attempt + 1} after {wait}s", flush=True)
            time.sleep(wait)
        except Exception as exc:
            wait = 2 + attempt
            print(f"  [err] {label!s} {exc!s} retry {attempt + 1}", flush=True)
            time.sleep(wait)
    return text


def translate_many(
    translator: GoogleTranslator,
    texts: list[str],
    *,
    progress_prefix: str,
    start_idx: int,
    total: int,
) -> list[str]:
    """Translate list using BATCH_SIZE; always falls back to one-by-one on failure."""
    if not texts:
        return []
    out: list[str] = []
    progress_every = max(25, BATCH_SIZE * 5)
    i = 0
    while i < len(texts):
        chunk = texts[i : i + BATCH_SIZE]
        if len(chunk) == 1:
            out.append(
                translate_one(
                    translator,
                    chunk[0],
                    label=f"{progress_prefix} [{start_idx + i + 1}/{total}]",
                )
            )
            done = len(out)
            if done % progress_every == 0 or done == total:
                print(
                    f"  {progress_prefix}: {done}/{total}",
                    flush=True,
                )
            i += 1
            time.sleep(SLEEP_SEC)
            continue

        prepared_chunks: list[str] = []
        token_maps: list[list[str]] = []
        for t in chunk:
            p, tm = protect_tokens(t)
            prepared_chunks.append(p)
            token_maps.append(tm)

        ok = False
        for attempt in range(3):
            try:
                with ThreadPoolExecutor(max_workers=1) as ex:
                    fut = ex.submit(translator.translate_batch, prepared_chunks)
                    batch_result = fut.result(timeout=TIMEOUT_SEC * max(2, len(chunk)))
                if isinstance(batch_result, str):
                    batch_result = [batch_result]
                if isinstance(batch_result, list) and len(batch_result) == len(chunk):
                    for j, translated in enumerate(batch_result):
                        tr = translated if isinstance(translated, str) else chunk[j]
                        out.append(restore_tokens(tr, token_maps[j]))
                    ok = True
                    break
            except Exception:
                time.sleep(2 + attempt)

        if not ok:
            for j, t in enumerate(chunk):
                out.append(
                    translate_one(
                        translator,
                        t,
                        label=f"{progress_prefix} [{start_idx + i + j + 1}/{total}]",
                    )
                )
                time.sleep(SLEEP_SEC)
                done = len(out)
                if done % progress_every == 0 or done == total:
                    print(
                        f"  {progress_prefix}: {done}/{total}",
                        flush=True,
                    )

        i += len(chunk)
        if ok:
            done = len(out)
            if done % progress_every == 0 or done == total:
                print(
                    f"  {progress_prefix}: {done}/{total}",
                    flush=True,
                )
            time.sleep(SLEEP_SEC)

    return out


def translate_wp_locale(locale: str, target_lang: str) -> tuple[int, int]:
    po_path = WP_LANG_DIR / f"ebq-seo-{locale}.po"
    po = polib.pofile(str(po_path))

    singular_entries = [
        e
        for e in po
        if not e.obsolete and not e.msgid_plural and e.msgid != ""
    ]
    plural_entries = [e for e in po if not e.obsolete and e.msgid_plural]

    translator = GoogleTranslator(source="en", target=target_lang)

    singular_texts = [e.msgid for e in singular_entries]
    total_s = len(singular_texts)
    singular_results = translate_many(
        translator,
        singular_texts,
        progress_prefix=f"WP {locale} sg",
        start_idx=0,
        total=total_s,
    )

    for entry, translated in zip(singular_entries, singular_results):
        entry.msgstr = translated or entry.msgid

    plural_singular_texts = [e.msgid for e in plural_entries]
    plural_plural_texts = [e.msgid_plural or "" for e in plural_entries]
    total_p = len(plural_entries)

    plural_singular_results = translate_many(
        translator,
        plural_singular_texts,
        progress_prefix=f"WP {locale} pl1",
        start_idx=0,
        total=total_p,
    )
    plural_plural_results = translate_many(
        translator,
        plural_plural_texts,
        progress_prefix=f"WP {locale} pl2",
        start_idx=0,
        total=total_p,
    )

    for entry, s0, s1 in zip(plural_entries, plural_singular_results, plural_plural_results):
        entry.msgstr_plural[0] = s0 or entry.msgid
        entry.msgstr_plural[1] = s1 or (entry.msgid_plural or "")

    po.metadata["Language"] = locale
    po.metadata["Last-Translator"] = "Machine translation (Google via deep-translator)"
    po.metadata["Language-Team"] = "EBQ AI Localization"
    po.save(str(po_path))
    po.save_as_mofile(str(po_path.with_suffix(".mo")))

    return len(singular_entries), len(plural_entries)


def read_json(path: Path) -> dict[str, str]:
    if not path.exists():
        return {}
    with path.open("r", encoding="utf-8-sig") as f:
        data = json.load(f)
    return {str(k): str(v) for k, v in data.items()}


def write_json(path: Path, data: dict[str, str]) -> None:
    ordered = dict(sorted(data.items(), key=lambda item: item[0].lower()))
    with path.open("w", encoding="utf-8", newline="\n") as f:
        json.dump(ordered, f, ensure_ascii=False, indent=2)
        f.write("\n")


def translate_platform_locale(app_locale: str, target_lang: str, en_data: dict[str, str]) -> int:
    if app_locale == "en":
        write_json(APP_LANG_DIR / "en.json", en_data)
        return len(en_data)

    translator = GoogleTranslator(source="en", target=target_lang)
    keys = list(en_data.keys())
    src_values = [en_data[k] for k in keys]
    results = translate_many(
        translator,
        src_values,
        progress_prefix=f"APP {app_locale}",
        start_idx=0,
        total=len(src_values),
    )
    translated = {k: (v or en_data[k]) for k, v in zip(keys, results)}
    write_json(APP_LANG_DIR / f"{app_locale}.json", translated)
    return len(translated)


def main() -> int:
    en_json = APP_LANG_DIR / "en.json"
    en_data = read_json(en_json)
    if not en_data:
        print("No base en.json translations found.")
        return 1

    print(
        f"Settings: BATCH_SIZE={BATCH_SIZE} SLEEP_SEC={SLEEP_SEC} "
        f"TIMEOUT_SEC={TIMEOUT_SEC}",
        flush=True,
    )

    print("Starting WP plugin translation pass...", flush=True)
    for locale in LOCALES:
        target = GOOGLE_LANG[locale]
        if target == "en":
            continue
        print(f"\n=== WP {locale} -> {target} ===", flush=True)
        s_count, p_count = translate_wp_locale(locale, target)
        print(f"[WP] {locale}: singular={s_count}, plural={p_count}", flush=True)

    print("\nStarting platform JSON translation pass...", flush=True)
    for wp_locale in LOCALES:
        app_locale = APP_LOCALE_MAP[wp_locale]
        target = GOOGLE_LANG[wp_locale]
        print(f"\n=== APP {app_locale} ===", flush=True)
        count = translate_platform_locale(app_locale, target, en_data)
        print(f"[APP] {app_locale}: entries={count}", flush=True)

    print("\nAll locale translations generated.", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
