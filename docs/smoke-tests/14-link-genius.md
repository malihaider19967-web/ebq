# 14 — Link Genius (orphan finder, broken-link, anchor bulk replace)

**MOAT lever:** Computation lock-in (we crawl the link graph) + data
gravity (per-site link index gets richer over time).

## What the feature does

Surfaces the internal-link health of a site in one admin screen:

- **Orphan posts** — posts with zero incoming internal links.
- **Broken links** — internal + external 4xx/5xx links.
- **Anchor bulk replace** — operator-defined rules that rewrite anchor
  text site-wide and add internal links to a target URL.
- **Auto-link on publish** — `save_post` fires
  `LinkGeniusController::applyAutoRules` so newly-published content
  picks up active rules without manual intervention.

## Files + endpoints + tables

- WP plugin: [`ebq-seo-wp/includes/class-ebq-link-genius-page.php`](../../ebq-seo-wp/includes/class-ebq-link-genius-page.php)
- Backend: [`app/Http/Controllers/Api/V1/LinkGeniusController.php`](../../app/Http/Controllers/Api/V1/LinkGeniusController.php)
- Crawler: [`app/Services/LinkGenius/CrawlerService.php`](../../app/Services/LinkGenius/CrawlerService.php)
- Jobs: [`app/Jobs/LinkGenius/CrawlWebsiteJob.php`](../../app/Jobs/LinkGenius/CrawlWebsiteJob.php), [`RecomputeOrphansJob`](../../app/Jobs/LinkGenius/RecomputeOrphansJob.php), [`ApplyAnchorRuleJob`](../../app/Jobs/LinkGenius/ApplyAnchorRuleJob.php)
- Tables: `link_genius_links`, `link_genius_anchor_rules`, `keyword_link_maps`
- Plan flag: `plan_features.link_genius`

## Pre-conditions

- Plan has `link_genius` enabled (Pro or above by default).
- `php artisan migrate` has been run so the three tables exist.

## Scenarios

### 1. Plan-gate happy path (Pro)

```bash
curl -H "Authorization: Bearer <SITE_TOKEN>" \
  https://ebq.io/api/v1/link-genius/overview
```

✅ Expect HTTP 200 with `{ ok: true, summary: { total_internal_links, broken_internal_links, broken_external_links, orphan_posts } }`.

### 2. Plan-gate failure (Free)

```bash
curl -H "Authorization: Bearer <FREE_SITE_TOKEN>" \
  https://ebq.io/api/v1/link-genius/overview
```

✅ Expect HTTP 402 + `{ error: "tier_required", required_tier: "pro" }`.

### 3. Anchor rule lifecycle

```bash
# Create a rule
curl -XPOST -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"anchor_pattern":"keyword","replacement_url":"https://example.com/","status":"active"}' \
  https://ebq.io/api/v1/link-genius/anchor-rules

# Apply it
curl -XPOST -H "Authorization: Bearer <TOKEN>" \
  https://ebq.io/api/v1/link-genius/anchor-rules/<ID>/apply

# Verify count + last_applied_at update
php artisan tinker --execute="echo \App\Models\LinkGeniusAnchorRule::find(<ID>)->applied_count"
```

✅ `applied_count` increments and `last_applied_at` is current.

### 4. Auto-link on publish

In wp-admin, publish a new post that contains the rule's
`anchor_pattern`. Within a few seconds the WP-side `save_post` hook
calls `/link-genius/apply-auto-rules`. Check the Laravel log:

```bash
tail -f storage/logs/laravel.log | grep "LinkGenius anchor rule applied"
```

✅ A log line is emitted with the rule + post IDs.

## Common failure modes

| Symptom | Cause | Fix |
|---|---|---|
| `not_migrated` 503 | Tables missing | `php artisan migrate` |
| Empty overview | No crawl yet | Dispatch `CrawlWebsiteJob` once |
| 402 on Pro plan | Per-site override disabled flag | Toggle on in `/admin/website-features` |
