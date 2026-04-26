# 04 — WP transient + LiteSpeed/CDN cache hardening

**MOAT lever:** correctness (not strictly MOAT, but a precondition for
every other live feature being trustworthy). Stale responses break the
"live" promise faster than any missing factor would.

## Surface

| Layer | Path |
|---|---|
| WP API client (transient cache) | `ebq-seo-wp/includes/class-ebq-api-client.php::get()` (skip-cache list + `handle_response()` skip) |
| WP REST proxy (LSCache + headers + POST) | `ebq-seo-wp/includes/class-ebq-rest-proxy.php` (`maybe_disable_cache_for_dynamic_routes`, `maybe_apply_nocache_headers`) |
| React fetcher (POST + cache-bust) | `ebq-seo-wp/src/sidebar/components/LiveSeoScore.jsx` |

## Pre-conditions

- Site has LiteSpeed Cache plugin active (or any WP page-cache plugin —
  the code path is unconditional).
- A logged-in WP admin user.

## Scenario 1 — Internal transient cache skip

```bash
# On the WP host
wp transient list --search=ebq_api 2>/dev/null | grep seo-score
# After hitting the score endpoint, NO transient row should exist for /seo-score.
# Allowed transients are for non-dynamic endpoints (post-insights, etc.)
```

Or in PHP:

```php
// Should return false even right after a score call:
get_transient('ebq_api_v...md5(seo-score URL)...');
```

**Pass when:** no transient is created for `/seo-score`, `/topical-gaps`,
`/entity-coverage`, or `/api/v1/hq/*` paths. Other editor endpoints
(focus-keyword-suggestions, internal-link-suggestions) MAY still cache.

## Scenario 2 — Header stack on the live response

```bash
curl -i -X POST "https://your-site.com/wp-json/ebq/v1/seo-score/<POST_ID>" \
  -H "Cookie: <wp-admin-cookie>" -H "X-WP-Nonce: <nonce>" 2>&1 | grep -iE "cache-control|pragma|expires|x-litespeed|x-accel|cdn-cache"
```

**Pass when** all of these headers are present:

```
Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private
Pragma: no-cache
Expires: Thu, 01 Jan 1970 00:00:00 GMT
X-Accel-Expires: 0
CDN-Cache-Control: no-store
Cloudflare-CDN-Cache-Control: no-store
X-LiteSpeed-Cache-Control: no-cache
```

## Scenario 3 — LiteSpeed action hooks fire

LiteSpeed reads `litespeed_control_set_nocache` action even when the
header is ignored. Verify by checking the LSCache debug log (Admin →
LiteSpeed Cache → Toolbox → Debug Settings → enable). After hitting
the endpoint:

```
[Cache] _no_cache : 1 ; reason : EBQ dynamic plugin endpoint
[Cache] _vary : ...
```

**Pass when** the no_cache flag is set BEFORE the response is built.

## Scenario 4 — Method = POST (not GET)

Inspect the WP plugin's React Network tab in DevTools. The `seo-score`
request method **must** be `POST`. POST responses are physically
uncacheable per HTTP spec.

```bash
# Server-side confirmation that POST is registered:
grep -A 2 "seo-score" /var/www/wp-content/plugins/ebq-seo-wp/includes/class-ebq-rest-proxy.php | grep methods
# Should show: 'methods' => ['GET', 'POST']  (GET kept for back-compat)
```

## Scenario 5 — Scoping to plugin endpoints only

Other plugins' REST routes must not be affected by our cache headers.

```bash
# Hit a generic WP REST endpoint
curl -i "https://your-site.com/wp-json/wp/v2/posts/<POST_ID>" -H "Cookie: ..." 2>&1 | grep -iE "x-litespeed-cache-control|x-accel-expires"
# Should return NOTHING — our headers only apply to /ebq/v1/* dynamic routes
```

## Acceptance summary

| Check | Pass condition |
|---|---|
| Transient skip list covers `/seo-score`, `/topical-gaps`, `/entity-coverage`, `/api/v1/hq/*` | No transients written for these paths |
| Full no-cache header stack present on response | All 7 headers in Scenario 2 |
| LiteSpeed action hook fires | LSCache debug log shows `_no_cache: 1` |
| POST method registered | `class-ebq-rest-proxy.php` has `'methods' => ['GET', 'POST']` for seo-score |
| No global side effects | `/wp-json/wp/v2/*` responses unchanged |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| Score updates only after manual purge | LSCache "Cache REST API" enabled + headers ignored | Deploy the action-hook code; OR exclude `/wp-json/ebq/v1/` in LSCache UI |
| Score frozen but headers look right | Internal WP transient cache (the actual culprit in our prior bug) | Check `class-ebq-api-client.php::get()` skip-cache list includes the path |
| Stale response from CDN | Cloudflare APO ignored Cache-Control | Verify `CDN-Cache-Control: no-store` header is present |
| Headers missing entirely | Plugin not deployed or REST proxy not registered | `grep maybe_apply_nocache_headers /var/www/...class-ebq-rest-proxy.php` |
