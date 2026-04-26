# 06 — AI title + meta rewrites

**MOAT lever:** AI-native + computation lock-in. Prompt + few-shot +
competitor-SERP grounding all live on EBQ; plugin only sees output.

## Surface

| Layer | Path |
|---|---|
| Service | `app/Services/AiSnippetRewriterService.php` |
| Controller | `PluginInsightsController::rewriteSnippet()` (Pro-tier-gated) |
| Route | `POST /api/v1/posts/{id}/rewrite-snippet` |
| WP proxy | `ebq-seo-wp/includes/class-ebq-rest-proxy.php::rewrite_snippet()` |
| WP route | `POST /wp-json/ebq/v1/rewrite-snippet/{id}` |
| React | `ebq-seo-wp/src/sidebar/components/AiRewriteSnippet.jsx` |

## Pre-conditions

- `MISTRAL_API_KEY` set in `.env`.
- Test website on Pro tier (see [05](05-tier-gating.md)).
- Test post has a focus keyphrase + ≥50 chars of content.

## Scenario 1 — Happy path

```bash
curl -s -X POST "https://ebq.io/api/v1/posts/<POST_ID>/rewrite-snippet" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "focus_keyword":"vegan protein powder",
    "current_title":"Vegan Protein Powder Guide",
    "current_meta":"All about vegan protein.",
    "content_excerpt":"Vegan protein powders are made from plant sources like pea, rice, soy, hemp. They provide complete amino acid profiles when blended. Top brands include..."
  }' | jq '.rewrite'
```

**Pass when:**
- `ok: true`
- `rewrites[]` has exactly 3 entries, each with non-empty `title` (≤90),
  `meta` (≤200), `rationale`, and `angle` (one of: commercial,
  informational, curiosity, comparison, guide).
- `cached: false` on first call.

## Scenario 2 — Re-click within 7 days hits cache

Repeat the same curl. **Pass when** `cached: true` and zero new
`client_activities` rows for `mistral` provider.

## Scenario 3 — Free tier rejected

Set the website to free, repeat the curl. **Pass when** HTTP 402 +
`error: "tier_required"`.

## Scenario 4 — Editor "Use title / Use meta / Use both"

In the WP editor on a Pro site:
1. Open SEO tab.
2. Click "Improve with AI" — modal opens with 3 cards.
3. Click "Use title" on card 1 → SEO title field populates.
4. Click "Use meta" on card 2 → meta description populates.
5. Click "Use both" on card 3 → both populate (overwriting).

**Pass when** the post-meta values change in the underlying
`get_post_meta($id, '_ebq_title')` / `_ebq_description`.

## Acceptance summary

| Check | Pass condition |
|---|---|
| 3 rewrites returned with rationale + angle | `rewrites.length === 3` |
| Title/meta length caps respected | `len(title) ≤ 90`, `len(meta) ≤ 200` |
| Cache works | `cached: true` on second call |
| Tier gate works | 402 for free, 200 for pro |
| Editor mutates post meta | `_ebq_title` / `_ebq_description` updated |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| `error: "llm_parse_failed"` | Mistral returned text the tolerant parser still couldn't decode | `tail laravel.log \| grep "Mistral completeJson"` for raw_preview |
| `error: "missing_focus_keyword"` | Form validator caught empty focus | Type a focus keyphrase in the editor |
| Modal opens but never loads | Auth failed (401) or proxy 500 | Check Network tab; if 500 see [01](01-live-seo-score.md) common-failures section |
