# 26 — Instant Indexing admin page

## What the feature does

A dedicated wp-admin page where operators paste up to 100 URLs and
push them straight to the EBQ Indexing API (which fans them out to
Google Indexing API + IndexNow). Each submission is recorded in a
rolling per-site log on the page.

## Files

- WP page: [`ebq-seo-wp/includes/class-ebq-instant-indexing-page.php`](../../ebq-seo-wp/includes/class-ebq-instant-indexing-page.php)
- API client glue: [`ebq-seo-wp/includes/class-ebq-api-client.php`](../../ebq-seo-wp/includes/class-ebq-api-client.php) → `instant_indexing_submit()`
- Backend: [`app/Http/Controllers/Api/V1/InstantIndexingController.php`](../../app/Http/Controllers/Api/V1/InstantIndexingController.php) (delegates to PluginHq)
- Plan flag: `plan_features.instant_indexing`

## Pre-conditions

- Plan has `instant_indexing` on (Pro+).
- Plugin connected to a workspace (token + workspace id present).

## Scenarios

### 1. Page accessibility

EBQ HQ → Instant indexing.

✅ A textarea appears with a "Submit URLs" primary button. Below the
form, the "Recent submissions" log table lists prior runs (empty on
fresh install).

### 2. Submit a URL

Paste a published post URL into the textarea, click Submit.

✅ Page reloads with a green success banner showing 1 OK / 0 failed.
A new log row records the URL + timestamp + status `ok`.

### 3. Backend round-trip

```bash
tail -n 50 storage/logs/laravel.log | grep "indexing/submit"
```

✅ A POST to `/api/v1/hq/indexing/submit` is logged with HTTP 200.

### 4. Failure path

Submit `https://no-such-host.invalid/`.

✅ Banner shows 0 OK / 1 failed; log row's status is `error` with
`api_failed`.

### 5. Plan gate

Switch the plan to Free:

✅ The "Instant indexing" submenu disappears. Direct API calls
return HTTP 402 with `tier_required`.
