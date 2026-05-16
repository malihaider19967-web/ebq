# 21 — Sitewide SEO Analyzer

## What the feature does

Runs a multi-check site audit (homepage HTTP, robots.txt, XML
sitemap reachable, schema validation, broken-link sample, Core Web
Vitals via PSI). Persists each run; surfaces pass/warn/fail cards in
the WP "SEO Analyzer" admin page.

## Files

- WP: [`ebq-seo-wp/includes/class-ebq-audit-page.php`](../../ebq-seo-wp/includes/class-ebq-audit-page.php)
- Backend: [`app/Http/Controllers/Api/V1/SiteAuditController.php`](../../app/Http/Controllers/Api/V1/SiteAuditController.php)
- Table: `site_audit_runs`
- Plan flag: `plan_features.sitewide_audit`

## Pre-conditions

- Migration `2026_05_18_002000_create_site_audit_runs_table` ran.
- Plan has `sitewide_audit` on (Agency by default).

## Scenarios

### 1. Trigger a run

EBQ HQ → SEO Analyzer → Run audit now.

✅ Page reloads; under "Latest run" cards render with at least 6
checks. Each card shows status (PASS/WARN/FAIL) + message.

### 2. Persistence

```bash
php artisan tinker --execute="echo \DB::table('site_audit_runs')->where('website_id', <ID>)->count();"
```

✅ Count is ≥1.

### 3. Plan gate

Switch the plan to Startup. The "SEO Analyzer" submenu disappears
and the API returns HTTP 402 on direct call.

```bash
curl -H "Authorization: Bearer <STARTUP_TOKEN>" \
  -X POST https://ebq.io/api/v1/audit/run
```

✅ Returns `{ error: "tier_required", required_tier: "agency" }`.
