# 05 — Tier gating + reactive sync

**MOAT lever:** monetization layer for every Pro AI feature. The gating
itself doesn't add MOAT, but it's what lets the AI MOAT generate revenue.

## Surface

| Layer | Path |
|---|---|
| Migration | `database/migrations/2026_04_27_100000_add_tier_to_websites_table.php` |
| Model | `app/Models/Website.php` (`TIER_FREE`, `TIER_PRO`, `isPro()`) |
| Connect | `app/Http/Controllers/WordPressConnectController.php` (`ebq_tier` in callback URL) |
| WP receiver | `ebq-seo-wp/includes/class-ebq-connect.php` (writes `ebq_site_tier` option) |
| WP localizer | `class-ebq-gutenberg-sidebar.php` + `class-ebq-seo-fields-meta-box.php` |
| Reactive hook | `ebq-seo-wp/src/sidebar/hooks/useTier.js` (apiFetch middleware + event) |
| Auto-sync sink | `ebq-seo-wp/includes/class-ebq-api-client.php::handle_response()` |

## Pre-conditions

- `tier` column exists on `websites` (run migration if not).
- Connect flow completed at least once for the test site (so
  `ebq_site_token` is set).

## Scenario 1 — Free tier UI

Set the website to free in DB:

```sql
UPDATE websites SET tier = 'free' WHERE id = <WEBSITE_ID>;
```

Reload the WP editor. **Pass when:**
- SeoTab shows "AI title + meta rewrites are on Pro. Upgrade →" (NOT the
  "Improve with AI" button).
- Brief tab shows the upgrade EmptyState.

## Scenario 2 — Set tier=Pro in DB → next API call flips UI without reload

```sql
UPDATE websites SET tier = 'pro' WHERE id = <WEBSITE_ID>;
```

In the editor (don't reload), edit any field that triggers a score
fetch (e.g., update the focus keyword). The `seo-score` response now
returns `tier: "pro"`.

**Watch for:**
1. The apiFetch middleware in `useTier.js` mutates
   `window.ebqSeoPublic.tier = 'pro'` and dispatches `ebq:tier-changed`.
2. SeoTab + BriefTab subscribe → re-render with the AI buttons in place
   of the upgrade CTA.
3. WP option also updates: `wp option get ebq_site_tier` → `pro`.

**Pass when** all three happen within one editor save.

## Scenario 3 — Server-side gate (defense in depth)

Even if the client sent a Pro request without entitlement, the server
must reject it. Force `tier='free'` in DB then call:

```bash
curl -s -X POST "https://ebq.io/api/v1/posts/<POST_ID>/rewrite-snippet" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"focus_keyword":"x","content_excerpt":"hello world this is content text used to satisfy the validator min length"}' \
  -i
```

**Pass when:** HTTP 402 with body:

```json
{ "ok": false, "error": "tier_required", "tier": "free", "required_tier": "pro", "message": "..." }
```

## Scenario 4 — `tier` echoed on every dynamic response

```bash
curl -s -X POST "https://ebq.io/api/v1/posts/<POST_ID>/seo-score?url=<URL>" \
  -H "Authorization: Bearer <TOKEN>" | jq '.tier'
# => "pro" or "free"
```

## Acceptance summary

| Check | Pass condition |
|---|---|
| Tier column on websites table | `DESCRIBE websites` shows `tier varchar(16)` |
| Free tier shows upgrade CTA | SeoTab + BriefTab render upgrade copy when `tier='free'` |
| Pro tier shows feature buttons | "Improve with AI" + Brief tab functional when `tier='pro'` |
| Server gates Pro endpoints | 402 with `tier_required` for free-tier callers |
| Reactive sync without reload | DB flip → next API response → React re-renders within one editor save |
| WP option auto-syncs | `ebq_site_tier` updated by `handle_response()` |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| User upgraded but still sees Free UI | Static `cfg.tier` read instead of `useTier()` | Verify SeoTab + BriefTab call `useTier()` and use that value, not `cfg.tier` |
| Tier flips back to Free after reload | wp_localize_script not picking up updated option | `grep ebq_site_tier ebq-seo-wp/includes/class-ebq-gutenberg-sidebar.php` should show it |
| 402 on Pro tier | Whitelist mismatch | `Website::isPro()` returns `tier === 'pro'`. Verify DB value is exactly `'pro'` (lowercase, no whitespace). |
