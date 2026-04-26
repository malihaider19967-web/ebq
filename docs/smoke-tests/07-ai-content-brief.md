# 07 — AI content brief

**MOAT lever:** AI-native + computation + data-gravity. Combines paid
Serper SERP + LLM extraction + per-site GSC join — three EBQ-only
ingredients. Plugin can't reproduce any of them offline.

## Surface

| Layer | Path |
|---|---|
| Service | `app/Services/AiContentBriefService.php` |
| Controller | `PluginInsightsController::contentBrief()` (Pro-tier-gated) |
| Route | `POST /api/v1/posts/{id}/content-brief` |
| WP proxy | `ebq-seo-wp/includes/class-ebq-rest-proxy.php::content_brief()` |
| WP route | `POST /wp-json/ebq/v1/content-brief/{id}` |
| React | `ebq-seo-wp/src/sidebar/tabs/BriefTab.jsx` |

## Pre-conditions

- Mistral + Serper keys set.
- Pro tier on the test site.
- Some `search_console_data` for the site (so internal-link targets
  populate; otherwise that section is empty but the brief still works).

## Scenario 1 — Generate brief from a keyword

```bash
curl -s -X POST "https://ebq.io/api/v1/posts/<POST_ID>/content-brief" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"focus_keyword":"how to write a content brief"}' | jq '.brief'
```

**Pass when** `ok: true` and these fields populated:
- `keyword`, `country`, `angle`
- `recommended_word_count` (integer 600..4500)
- `suggested_schema_type` (one of: Article, HowTo, FAQ, Review, Product, Recipe, Event, Course, LocalBusiness)
- `subtopics` (8–14 items)
- `must_have_entities` (up to 12)
- `suggested_outline` (6–10 H2 titles)
- `people_also_ask` (5–10 questions)
- `internal_link_targets` (0+ items from your GSC footprint)
- `serp_titles` (the actual top-10 titles from Serper)

## Scenario 2 — Cache hit on re-run

Repeat the curl with the same keyword + country. **Pass when**
`cached: true` and no new Serper / Mistral activity in
`client_activities`.

## Scenario 3 — Internal-link targets

Run the brief for a keyword that overlaps with your site's existing
GSC queries (e.g., a known-ranking topic). **Pass when**
`internal_link_targets[]` has at least 1 row with the structure:

```json
{ "url": "https://your-site.com/...", "anchor_hint": "matched query string", "clicks_30d": 42 }
```

These are URLs on the user's OWN site that already rank for queries
related to the brief topic. They're suggested as internal-link targets.

## Scenario 4 — Editor flow

In the WP editor on a Pro site:
1. Open the new "Brief" tab (between SEO and Readability).
2. Type a keyword, click "Generate brief".
3. Wait 15–25s for the spinner.
4. **Pass when** all sections render: overview stats grid, H2 outline,
   subtopic chips, entity chips, PAA list, internal-link targets.

## Acceptance summary

| Check | Pass condition |
|---|---|
| Brief schema fully populated | All 10 fields present |
| Word count within bounds | `recommended_word_count` ∈ [600, 4500] |
| Schema type from whitelist | One of the 9 allowed values |
| 7-day cache works | `cached: true` on repeat call |
| GSC bolt-on works | `internal_link_targets[]` non-empty when GSC has overlapping queries |
| Free tier rejected | 402 on free-tier websites |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| `error: "no_serp_data"` | Serper returned 0 organic results (rare niche / blocked region) | Try a different keyword to confirm pipeline; otherwise check Serper dashboard |
| `error: "llm_parse_failed"` | Mistral returned malformed JSON despite tolerant parser | Same fix as 06 — check `Mistral completeJson: parse failed` log |
| Internal link targets empty | No GSC overlap with brief topic | Expected — brief still useful, just no internal-link bolt-on |
| Brief tab not visible | App.jsx doesn't list `BriefTab` | `grep BriefTab ebq-seo-wp/src/sidebar/App.jsx` should hit |
