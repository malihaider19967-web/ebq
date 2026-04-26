# 03 — Audit auto-queue + lite mode + re-audit on update

**MOAT lever:** computation lock-in. The audit pipeline (HTML fetch +
Lighthouse + Serper benchmark + KeywordStrategyAnalyzer +
RecommendationEngine) is the heart of the live-score factor mix.

## Surface

| Layer | Path |
|---|---|
| Service | `app/Services/PageAuditService.php` (`$lite` flag) |
| Job | `app/Jobs/RunCustomPageAudit.php` |
| Trigger | `LiveSeoScoreService::resolveAuditState()` |
| Queue model | `App\Models\CustomPageAudit` (`SOURCE_LIVE_SCORE`) |
| Result table | `page_audit_reports` |
| Plugin sender | `ebq-seo-wp/includes/class-ebq-rest-proxy.php::seo_score()` (forwards `post_modified_at`) |
| Frontend | `ebq-seo-wp/src/sidebar/components/LiveSeoScore.jsx` (polls 6s on `queued|running|refreshing`) |

## Pre-conditions

- Queue worker running (`ps aux | grep queue:work`).
- Mistral / Lighthouse / Serper keys configured for the full pipeline
  (lite mode skips Serper benchmark + link checking — still works without).
- `ebq_site_token` set on the WP site so the post_modified_at param flows.

## Scenario 1 — First-ever audit auto-queues + completes lite

```bash
# Hit score for a URL with no PageAuditReport
curl -s -X POST "https://ebq.io/api/v1/posts/<POST_ID>/seo-score?url=<URL>" \
  -H "Authorization: Bearer <TOKEN>" | jq '.live.audit'
```

**Immediate response:**

```json
{ "status": "queued", "message": "EBQ is auditing this page for the first time…", "queued_at": "2026-04-..." }
```

Verify the queue row uses lite mode:

```sql
SELECT id, status, source, queued_at, started_at FROM custom_page_audits
WHERE page_url_hash = SHA2(?, 256) ORDER BY queued_at DESC LIMIT 1;
-- source MUST be 'live_score'
```

Wait 15–30s, re-curl. **Pass when:** `audit.status` flips to `"ready"`,
the 5 audit-derived factors come out of `pending`, and the new
`page_audit_reports` row has `result.benchmark` = null (lite mode skipped
Serper) but `result.core_web_vitals` populated.

## Scenario 2 — Post update triggers re-audit

After Scenario 1 lands, edit the post in WP admin (any change to body or
title) and save. The proxy reads `post_modified_gmt` server-side via
`get_post_modified_time('c', true)` and forwards it.

```bash
curl -s -X POST "https://ebq.io/api/v1/posts/<POST_ID>/seo-score?url=<URL>&post_modified_at=2026-04-28T12:00:00%2B00:00" \
  -H "Authorization: Bearer <TOKEN>" | jq '.live.audit'
```

**Pass when:**
- `audit.status: "refreshing"` (we still serve previous audit data while new one runs)
- `audit.previous_audited_at` populated
- Audit factors keep their previous values (not pending) until polling picks up the new completion

## Scenario 3 — Zombie audit gets bypassed

Force a stuck row:

```sql
UPDATE custom_page_audits SET status='running', started_at = NOW() - INTERVAL 30 MINUTE
WHERE id = <ROW_ID>;
```

Re-curl the score endpoint. **Pass when:** a NEW `custom_page_audits`
row gets queued (the zombie is older than the 15-min cap and is
ignored). Without this fix the score would loop on `refreshing` forever.

## Scenario 4 — Failed audit stops polling

```sql
UPDATE custom_page_audits SET status='failed', error_message='test failure' WHERE id = <ROW_ID>;
UPDATE page_audit_reports SET status='failed' WHERE id = <REPORT_ID>;
```

Re-curl. **Pass when:** `audit.status: "failed"`, `audit.message` echoes
the error. Frontend should stop polling (no `queued|running|refreshing`).

## Acceptance summary

| Check | Pass condition |
|---|---|
| First-time audit queues with `source='live_score'` | New `custom_page_audits` row |
| Lite mode skips Serper benchmark | `result.benchmark IS NULL`, `result.core_web_vitals` populated |
| Audit completes in <45s typically | `audited_at - queued_at < 45s` for most pages |
| Post edit triggers re-audit | `audit.status='refreshing'` after save |
| Zombie row >15 min ignored | Fresh row queued instead of looping |
| Failure status stops polling | Frontend caption shows error message |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| Audit never completes | Queue worker not running, or job uniqueness lock stuck | `php artisan queue:retry all` after fixing worker |
| Editor stuck on "refreshing" forever | Timezone bug (mysql2date instead of get_post_modified_time) OR clock skew >60s | Confirm proxy uses `get_post_modified_time('c', true, $post, false)`; service has 60s tolerance |
| Lite mode runs the full pipeline | Old `RunCustomPageAudit` checking `SOURCE_PAGE_DETAIL` instead of `SOURCE_LIVE_SCORE` | Deploy the latest job; `grep SOURCE_LIVE_SCORE app/Jobs/RunCustomPageAudit.php` |
