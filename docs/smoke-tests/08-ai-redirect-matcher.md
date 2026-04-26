# 08 — AI redirect matcher (404 capture → suggestions)

**MOAT lever:** AI-native + computation lock-in. Naive slug matching
(what RankMath / Redirection do) gets ~30% precision; LLM-grounded
matching with title context + traffic weight gets ~80%+. The whole
pipeline runs on EBQ.

## Surface

| Layer | Path |
|---|---|
| 404 capture | `ebq-seo-wp/includes/class-ebq-404-tracker.php` (registered in `class-ebq-plugin.php`) |
| Hourly drain cron | WP cron event `ebq_send_404_batch` |
| API client sender | `ebq-seo-wp/includes/class-ebq-api-client.php::report_404s()` |
| Receiver | `PluginInsightsController::report404s()` |
| Matcher service | `app/Services/AiRedirectMatcherService.php` |
| Job | `app/Jobs/MatchRedirectFor404Job.php` |
| Storage | `redirect_suggestions` table, model `App\Models\RedirectSuggestion` |
| HQ tab | `ebq-seo-wp/src/hq/tabs/RedirectSuggestionsTab.jsx` |
| HQ proxy | `ebq-seo-wp/includes/class-ebq-rest-proxy.php::redirect_suggestions_list/decide` |

## Pre-conditions

- Mistral key set.
- `redirect_suggestions` table migrated.
- WP plugin connected; queue worker running.
- Some `search_console_data` rows for the test website (matcher uses
  GSC URLs as candidate inventory).

## Scenario 1 — Front-end 404 captured

```bash
# On the connected WP site, visit a URL that doesn't exist:
curl -s "https://your-site.com/this-path-does-not-exist-12345" -o /dev/null

# Verify the buffer was written:
wp option get ebq_404_buffer
# Should show an array with the path + hits=1
```

**Pass when:** `ebq_404_buffer` option contains the path. Bot user
agents (curl with default UA) ARE filtered — for the smoke test, set a
real-browser UA:

```bash
curl -s "https://your-site.com/this-path-does-not-exist-12345" \
  -H "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36" \
  -o /dev/null
```

## Scenario 2 — Cron drain ships the buffer

```bash
wp cron event run ebq_send_404_batch
```

**Pass when:**
- `ebq_404_buffer` option deleted (or shrunk if more 404s came in mid-cron).
- A new row in `custom_page_audits` is NOT created (this is the redirect
  pipeline, not the audit pipeline). Instead:

```sql
SELECT id, status, source_path, suggested_destination, confidence
FROM redirect_suggestions
WHERE website_id = <WEBSITE_ID> ORDER BY created_at DESC LIMIT 5;
```

The row should appear with `status='pending'`, suggested destination
populated, confidence 0–100.

## Scenario 3 — Manual job dispatch (skip the front-end + cron)

In Tinker on EBQ:

```php
\App\Jobs\MatchRedirectFor404Job::dispatch(<WEBSITE_ID>, '/old-path-i-want-redirected', 5);
```

Wait for queue worker, then check `redirect_suggestions`. **Pass when**
the LLM picks a destination from the GSC inventory (validated against
the candidate set — never hallucinated paths).

## Scenario 4 — Idempotent re-runs

Re-dispatch the same job. **Pass when:**
- No second `redirect_suggestions` row created.
- `hits_30d` bumps on the existing row.
- `last_seen_at` updated.
- LLM NOT called again (within 30-day re-match window).

## Scenario 5 — HQ apply writes a local 301

In HQ → Redirects (AI):
1. Pending suggestion appears with the suggested destination.
2. Click "Apply".
3. **Pass when:**
   - Row removed from the pending list (status flipped to `applied`).
   - Local 301 rule appears in EBQ Redirects: `wp post list --post_type=ebq_redirect`
   - Visiting the original 404 path now 301s to the suggested destination.

## Acceptance summary

| Check | Pass condition |
|---|---|
| Front-end 404 captured | `ebq_404_buffer` option populated with path + hits |
| Bots filtered | Default-UA curl 404s do NOT populate the buffer |
| Cron drain ships to EBQ | Buffer cleared, `redirect_suggestions` row appears |
| LLM picks a real candidate | `suggested_destination` exists in `search_console_data.page` |
| Idempotent | Second job dispatch doesn't double-bill LLM |
| Apply creates local 301 | Front-end redirect serves immediately |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| Buffer never fills | `EBQ_404_Tracker::register()` not called, or plugin not configured | Check `class-ebq-plugin.php` registers it; verify `EBQ_Plugin::is_configured()` returns true |
| Cron never runs | WP cron disabled or hook not scheduled | `wp cron event list \| grep ebq_send_404_batch`; reschedule with `wp cron event run ebq_send_404_batch` |
| Suggestion has empty destination | GSC inventory has zero candidates for the site | Wait for more GSC sync; or accept that small/new sites have no inventory |
| LLM picks a hallucinated URL | Validation broken | `AiRedirectMatcherService::validateDestinationAgainstCandidates()` enforces membership; should be impossible |
