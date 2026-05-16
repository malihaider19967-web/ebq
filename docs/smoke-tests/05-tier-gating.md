# 05 — Plan-driven tier gating + reactive sync

**MOAT lever:** monetization layer for every paid feature. The gating
itself doesn't add MOAT, but it's what lets the AI MOAT generate revenue.

## Model (post-2026-05-17 rename)

`plans` is now the single source of truth for everything the WordPress
plugin reads. Tier ordering, lowest → highest:

```
free  <  pro  <  startup  <  agency
```

- `pro` is the entry-level paid tier (previously `starter`).
- `startup` is the growth tier (previously `pro`).
- `free` and `agency` are unchanged.

A user's tier is derived live from their Cashier subscription via
`User::effectivePlan()` → `Plan::slug`. The `websites.tier` column was
dropped; every consumer goes through `Website::effectiveTier()` which
delegates to the owning user (clamped to `free` when the site is
frozen).

Feature gating is now plan-driven. Each plan row carries a
`plan_features` JSON map keyed by the plugin feature flags. As of the
2026-05-18 Rank-Math-parity push the canonical key list is:

```
# core (8)
chatbot, ai_writer, ai_inline, live_audit, hq, redirects,
dashboard_widget, post_column

# Rank-Math-parity (15)
internal_links, link_genius, news_sitemap, local_multi, image_bulk,
woo_pro, analytics_pro, white_label, sitewide_audit, role_manager,
instant_indexing, llms_txt, speakable, schema_spy, schema_extras
```

Default tier mapping (see `database/seeders/PlanSeeder.php`):

| Tier | New flags ON by default |
|---|---|
| free | `llms_txt`, `speakable`, `schema_extras` |
| pro | + `internal_links`, `link_genius`, `woo_pro`, `role_manager`, `instant_indexing` |
| startup | + `news_sitemap`, `image_bulk`, `analytics_pro`, `schema_spy` |
| agency | + `local_multi`, `white_label`, `sitewide_audit` |

The map is edited live from `/admin/plans/<id>/edit` and consumed by:

- `Website::effectiveFeatureFlags()` — plan defines the ceiling; per-site
  overrides can narrow but cannot widen.
- `InjectFeatureFlags` middleware — emits the resolved `features` map
  on every authenticated JSON response.
- The WP plugin's `EBQ_Feature_Flags` cache — passively syncs the map
  on every API response.

## Surface

| Layer | Path |
|---|---|
| Plan migration | `database/migrations/2026_05_17_000000_add_plan_features_to_plans_table.php` |
| Slug rename | `database/migrations/2026_05_17_000100_rename_plan_slugs.php` |
| New-flag backfill | `database/migrations/2026_05_18_000000_seed_new_plan_features.php` |
| Plan model | `app/Models/Plan.php` (`FEATURE_KEYS` — 23 keys, `featureMap()`, `requiredPlanFor()`) |
| User model | `app/Models/User.php` (`effectivePlan()`, `effectiveTier()`, `effectivePlanFeatures()`, `isAtLeast()`) |
| Website model | `app/Models/Website.php` (`effectiveFeatureFlags()` — plan ceiling, `featureRequiresUpgrade()`) |
| Admin plan editor | `app/Http/Controllers/Admin/PlanController.php` + `resources/views/admin/plans/edit.blade.php` |
| Admin per-site grid | `app/Http/Controllers/Admin/WebsiteFeatureController.php` + `resources/views/admin/website-features/index.blade.php` |
| Middleware | `app/Http/Middleware/InjectFeatureFlags.php` (emits `tier` + `features` + `frozen` + `free_promo`) |
| Public plans API | `app/Http/Controllers/Api/V1/PricingController.php` (returns `plan_features`, `api_limits`, `max_websites`) |
| Marketing page | `resources/views/pricing.blade.php` (DB-driven, auto-includes list) |
| Connect callback | `app/Http/Controllers/WordPressConnectController.php` (`ebq_tier` carries the new slug) |
| WP receiver | `ebq-seo-wp/includes/class-ebq-connect.php` (writes `ebq_site_tier` option) |
| WP tier helper (PHP) | `ebq-seo-wp/includes/class-ebq-plugin.php` (`tier_at_least()`, `current_tier()`, `TIER_ORDER`) |
| WP tier helper (JS) | `ebq-seo-wp/src/block-editor-ai/index.js` (`tierAtLeast()`, `tierLabel()`, `TIER_ORDER`) |
| Auto-sync sink | `ebq-seo-wp/includes/class-ebq-api-client.php::handle_response()` (whitelist now `free|pro|startup|agency`) |

## Pre-conditions

- Both migrations applied: `2026_05_17_000000_add_plan_features_to_plans_table.php`
  + `2026_05_17_000100_rename_plan_slugs.php`.
- `PlanSeeder` run at least once (or `plan_features` filled in manually
  via `/admin/plans`).
- Connect flow completed for the test site (so `ebq_site_token` is set).

## Scenario 1 — Free tier UI lockout

Force the test user onto Free:

```sql
UPDATE users SET current_plan_slug = 'free' WHERE id = <USER_ID>;
DELETE FROM subscriptions WHERE user_id = <USER_ID>;
```

Reload the WP editor. **Pass when:**

- SeoTab shows "AI snippet rewrites require a Pro plan. Upgrade →"
  (no "Improve with AI" button).
- Brief tab shows the upgrade EmptyState.
- The block-toolbar "EBQ AI" menu items show snackbar "This action
  requires the **Pro** plan or above." (slug interpolated from
  `response.required_tier`).
- `wp option get ebq_site_tier` → `free`.

## Scenario 2 — Upgrade to Pro → next API call flips UI

```sql
UPDATE users SET current_plan_slug = 'pro' WHERE id = <USER_ID>;
```

Or — without DB poking — start a Stripe trial via /billing/checkout for
the `pro` plan.

In the editor (don't reload), edit any field that triggers a score
fetch. The `seo-score` response now returns `tier: "pro"`.

**Watch for:**
1. The apiFetch middleware updates `window.ebqSeoPublic.tier = 'pro'`
   and dispatches `ebq:tier-changed`.
2. `EBQ_Api_Client::handle_response` writes `ebq_site_tier = 'pro'`.
3. SeoTab + BriefTab re-render with the AI buttons in place of the
   upgrade CTA.

**Pass when** all three happen within one editor save.

## Scenario 3 — Startup-only feature gating

AI Writer (full-draft generator) is gated to `ai_writer = true` plans.
Default seed enables it on `startup` and `agency`, NOT on `pro`.

Set the user to `pro` and call:

```bash
curl -s -X POST "https://ebq.io/api/v1/posts/<POST_ID>/ai-writer" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"focus_keyword":"x"}' \
  -i
```

**Pass when:** HTTP 402 with body:

```json
{
  "ok": false,
  "error": "tier_required",
  "tier": "pro",
  "required_tier": "startup",
  "feature": "ai_writer",
  "message": "AI Writer is available on Pro. Upgrade to unlock."
}
```

`required_tier` MUST be `startup` (not the hardcoded `pro` from before
the rollout). The block-editor snackbar should render the CTA "Upgrade
to **Startup**" — not "Upgrade to Pro" — because the JS reads
`response.required_tier` through `aiErrorActions()`.

## Scenario 4 — Per-site override clamping

Admin opens `/admin/website-features` for a site owned by a Free user.
Every row marked `Needs <plan>` is disabled (the user's plan doesn't
include it). Toggling a row that IS allowed by the plan to OFF persists
the override; on the next plugin API call the WP plugin's feature flag
for that key flips to false.

Toggling a `Needs pro` row remains disabled in the UI; if the admin
somehow POSTs a `true` value for it, `Website::effectiveFeatureFlags()`
clamps the read-time value to false (plan ceiling enforced server-side
regardless of override).

## Scenario 5 — Plan-features edit propagates

Edit `/admin/plans/<pro_plan_id>/edit` and disable the `chatbot` flag.
Save. Within seconds the WP plugin's next API call carries
`features.chatbot = false`. The chatbot FAB disappears from the editor
within 12 h at the latest (transient TTL); typically immediately if the
editor makes any roundtrip in the meantime.

## Scenario 6 — FREE=true promo pro-clone

Set the EBQ env `FREE=true` and `php artisan config:clear`. Every user —
even one with no Cashier subscription — now resolves to the Pro plan:

```bash
curl -s "https://ebq.io/api/v1/website-features" \
  -H "Authorization: Bearer <TOKEN>" | jq '.tier, .free_promo, .features'
# => "pro", true, {chatbot: true, ai_inline: true, ...}
```

Flip `FREE=false`, clear config, and the same call returns the user's
real plan slug (e.g. `free`) with the matching feature map (chatbot off
on Free per default seed).

## Acceptance summary

| Check | Pass condition |
|---|---|
| `plans.plan_features` column exists | `DESCRIBE plans` shows `plan_features json` |
| Slugs renamed | `SELECT slug FROM plans ORDER BY display_order` → `free, pro, startup, agency` |
| Free tier sees upgrade CTA | SeoTab + BriefTab render upgrade copy when user is `free` |
| Pro tier sees entry-level paid features | "AI inline edits" works at `pro`; AI Writer still gated to `startup` |
| `required_tier` honours plan map | `tier_required` payloads expose the slug returned by `Plan::requiredPlanFor()` |
| `feature` field accompanies `tier_required` | Every gating controller emits `feature: <flag_key>` alongside the slug |
| Plan-features clamps overrides | Per-site override of a plan-disallowed flag is ignored at read time |
| WP option auto-syncs | `ebq_site_tier` updated by `handle_response()` to the new slug taxonomy |
| Pricing page is DB-driven | Editing a plan in `/admin/plans` changes the public `/pricing` page after the 15-min CDN cache expires |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| User upgraded but still sees Free UI | Static `cfg.tier` read instead of `useTier()` | Verify SeoTab + BriefTab call `useTier()` and use that value, not `cfg.tier` |
| Tier flips back to Free after reload | wp_localize_script not picking up updated option | `grep ebq_site_tier ebq-seo-wp/includes/class-ebq-gutenberg-sidebar.php` should show it |
| 402 on what should be a paid endpoint | Plan's `plan_features.<key>` set to false in admin | Tick the checkbox in `/admin/plans/<id>/edit` and re-save |
| "Upgrade to Pro" copy never shows the right slug | Plugin bundle not rebuilt after the rollout | `cd ebq-seo-wp && npm run build` |
| Slug rename appears to have dropped subscriptions | Cashier `subscriptions` is keyed on Stripe price IDs, not slugs — the rename should be invisible | `User::effectivePlan()` resolves via `stripe_price_id_yearly` first; check that the Pro plan row's `stripe_price_id_yearly` matches what Stripe is sending |
