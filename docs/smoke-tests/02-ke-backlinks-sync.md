# 02 — Keywords Everywhere backlinks sync

**MOAT lever:** computation lock-in (paid KE API on EBQ). The plugin
never sees the KE key; it just reads the `backlinks` table the EBQ side
populated.

## Surface

| Layer | Path |
|---|---|
| Sync service | `app/Services/OwnBacklinkSyncService.php` |
| Background job | `app/Jobs/SyncOwnBacklinksFromKeywordsEverywhere.php` |
| Universal gate | `app/Services/BacklinkFreshnessGate.php` |
| KE client | `app/Services/KeywordsEverywhereBacklinkClient.php` |
| Trigger | `LiveSeoScoreService::score()` dispatches the job on every score request |
| Tables | `backlinks` (target), `competitor_backlinks` (gate also reads), Cache (`ke_backlinks_fetched:*`) |
| Config | `services.keywords_everywhere.backlinks_ttl_days` (env `KE_BACKLINKS_TTL_DAYS`, default 30) |

## Pre-conditions

- `KEYWORDS_EVERYWHERE_API_KEY` is set in `.env`.
- Test website's `domain` is reachable + has discoverable backlinks
  (small / brand-new sites legitimately return zero rows; the gate still
  marks the call fresh so you don't re-bill).
- Queue worker is running (`ps aux | grep queue:work`).

## Scenario 1 — First call dispatches a job

```bash
# Trigger via score endpoint
curl -s -X POST "https://ebq.io/api/v1/posts/<POST_ID>/seo-score?url=<URL>" \
  -H "Authorization: Bearer <TOKEN>" > /dev/null

# Verify the job is queued
php artisan queue:monitor default
```

Then in Tinker:

```php
\Illuminate\Support\Facades\Cache::has('ke_backlinks_fetched:example.com');
// => false on first call (job hasn't completed yet)
```

After the worker processes the job (~5–15s):

```php
\Illuminate\Support\Facades\Cache::has('ke_backlinks_fetched:example.com');
// => true
\App\Models\Backlink::where('website_id', <WEBSITE_ID>)->count();
// => N (any value > 0 if the domain has backlinks; 0 is also valid)
```

**Pass when:** cache flag is set after job runs AND `client_activities`
shows exactly one `keywords_everywhere` row for `operation:backlinks_for_domain`.

## Scenario 2 — Second call within 30 days = no KE hit

Repeat the curl from Scenario 1. In Tinker:

```php
\App\Models\ClientActivity::where('provider', 'keywords_everywhere')
    ->where('created_at', '>=', now()->subMinutes(5))
    ->count();
// => still 1 (no NEW activity row — the gate skipped the call)
```

**Pass when:** zero new `client_activities` rows recorded for the second
call — proves the freshness gate works.

## Scenario 3 — Force a re-fetch

```php
app(\App\Services\BacklinkFreshnessGate::class)->forget('example.com');
// then re-run the score curl — a new KE call should fire
```

## Scenario 4 — TTL override via env

```bash
echo 'KE_BACKLINKS_TTL_DAYS=7' >> /var/www/ebq/.env
sudo systemctl reload php-fpm
php artisan config:clear
```

In Tinker:

```php
app(\App\Services\BacklinkFreshnessGate::class)->ttlDays();
// => 7
```

## Acceptance summary

| Check | Pass condition |
|---|---|
| First call hits KE | `client_activities` row appears |
| Subsequent calls skip KE | No new activity row inside TTL window |
| Empty result still gates | Cache flag set even when KE returns zero rows |
| TTL env override works | `ttlDays()` reflects `KE_BACKLINKS_TTL_DAYS` |
| Universal gate covers competitor lookups | `CompetitorBacklinkService::refresh()` early-returns when gate says fresh |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| KE never called | Queue worker not running | `php artisan queue:work --once` to verify; restart supervisor |
| Same domain billed every score request | Fix not deployed | Confirm `BacklinkFreshnessGate` exists on box; `markFetched()` is called inside `OwnBacklinkSyncService::syncForWebsite()` |
| `Backlink` table empty after job | KE auth failed / no backlinks for domain | `tail -50 storage/logs/laravel.log \| grep KeywordsEverywhere` |
