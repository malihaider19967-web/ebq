# 13 — Entity coverage (E-E-A-T)

**MOAT lever:** AI-native + computation. The diff against competitor
entities requires (a) the audit pipeline, (b) the SERP benchmark
(Serper credits), (c) the LLM extraction. Plugin can't reproduce any
of the three offline.

## Surface

| Layer | Path |
|---|---|
| Service | `app/Services/EntityCoverageService.php` |
| Controller | `PluginInsightsController::entityCoverage()` |
| Route | `GET /api/v1/posts/{id}/entity-coverage?url=...` |
| WP proxy | `class-ebq-rest-proxy.php::entity_coverage()` |
| WP route | `GET /wp-json/ebq/v1/entity-coverage/{id}` |
| React | `ebq-seo-wp/src/sidebar/components/EntityCoverage.jsx` (in SeoTab) |
| Cache | `ebq_entity_coverage:<hash>` (7d TTL) |

## Pre-conditions

- Mistral key set.
- A completed `PageAuditReport` for the test URL with a populated
  `result.benchmark.competitors` (means the audit ran in NON-lite mode
  — i.e., it was triggered from HQ → Page Audits, not the editor's
  live-score auto-queue which uses lite mode).

## Scenario 1 — No audit yet

For a URL that's never been audited:

```bash
curl -s "https://ebq.io/api/v1/posts/<POST_ID>/entity-coverage?url=<URL>" \
  -H "Authorization: Bearer <TOKEN>" | jq
```

**Pass when** `entities.ok: false, entities.reason: "no_audit"`.
Frontend should render an "open the SEO tab to trigger an audit first"
message.

## Scenario 2 — Lite-mode audit (no benchmark)

If the only existing audit ran in lite mode (no competitor block):

**Pass when** `entities.ok: true` but the LLM's diff is one-sided —
`yours` populated, `competitors` empty, `missing` empty (because no
competitor pages were fetched).

## Scenario 3 — Full audit happy path

```bash
curl -s "https://ebq.io/api/v1/posts/<POST_ID>/entity-coverage?url=<URL>" \
  -H "Authorization: Bearer <TOKEN>" | jq '.entities'
```

**Pass when:**
- `ok: true`
- `yours[]` has 1+ entities (people, brands, products mentioned in YOUR page)
- `competitors[]` has 1+ entities (mentioned by top-3 ranking competitors)
- `missing[]` has 0–8 rows, each with `entity` (≤80 chars), `type`
  (one of: person, brand, product, org, place, framework, concept), and
  `why` (sentence explaining why it matters)

## Scenario 4 — Cache hit on re-run

Repeat the curl. **Pass when** `cached: true` and no new
`client_activities` rows for `mistral`.

## Scenario 5 — Editor sidebar

Open the WP editor → SEO tab → scroll to "Entity coverage (E-E-A-T)".

1. Click "Analyze entity coverage".
2. Wait 10–20s.
3. **Pass when:** summary stats render (you cover N · missing M ·
   competitor K), missing entities list each have type pill + why
   text, expandable details show all yours + all competitors as chips.

## Scenario 6 — Re-analyze with no content change

Click "Re-analyze" in the sidebar without editing the post. **Pass when**
the response comes back near-instantly (<1s) — that's the cache hit. The
component shows the same data and (silently) `cached: true`.

## Acceptance summary

| Check | Pass condition |
|---|---|
| Missing-audit case handled gracefully | `reason: "no_audit"` returned |
| Full audit returns three lists | `yours`, `competitors`, `missing` all populated |
| Type whitelist enforced | Each missing entity's `type` is in {person, brand, product, org, place, framework, concept} |
| 7d cache works | `cached: true` on repeat call within window |
| Editor renders all sections | Summary stats + missing list + expandable details + cache notice |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| `reason: "no_body_text"` | Audit's `result.content.body_text` empty | Re-run a non-lite audit from HQ → Page Audits |
| `competitors[]` empty even with full audit | Audit's `result.benchmark.competitors` is empty (Serper returned no results) | Check Serper response in the audit; if Serper is failing the wider topical-gaps feature will also break |
| `reason: "llm_parse_failed"` | Mistral output malformed | `tail laravel.log \| grep "EntityCoverageService"` for raw_preview |
| Same entities forever | 7d cache; edit content (changes content_hash → new cache key) or `Cache::forget('ebq_entity_coverage:<hash>')` |
