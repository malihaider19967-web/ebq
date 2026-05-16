# 23 — Schema Spy (import schema from URL)

## What the feature does

Pastes a competitor URL, fetches the page server-side, parses every
`<script type="application/ld+json">` block, and returns ready-to-
import schema entries the editor can multi-select into `_ebq_schemas`.

## Files

- WP REST proxy: [`ebq-seo-wp/includes/class-ebq-schema-spy.php`](../../ebq-seo-wp/includes/class-ebq-schema-spy.php)
- Backend: [`app/Http/Controllers/Api/V1/SchemaSpyController.php`](../../app/Http/Controllers/Api/V1/SchemaSpyController.php)
- Plan flag: `plan_features.schema_spy`

## Pre-conditions

- Plan has `schema_spy` on (Startup+).
- Test URL with at least one LD-JSON block (e.g. a major
  publisher's article page or a Shopify product).

## Scenarios

### 1. Happy path

```bash
curl -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com/article"}' \
  https://ebq.io/api/v1/schema-spy
```

✅ Returns `{ ok: true, count: N, entries: [{template, type, data, ...}, ...] }`.

### 2. Unknown @type fallback

URL with a custom `@type`:

✅ The corresponding entry has `template: "custom"` so the editor
imports it faithfully without losing fields.

### 3. Invalid URL

```bash
curl -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"url":"not-a-url"}' \
  https://ebq.io/api/v1/schema-spy
```

✅ HTTP 422 + `{ error: "invalid_url" }`.

### 4. Network failure

Use a non-existent host:

```bash
curl -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://no-such-host.invalid/"}' \
  https://ebq.io/api/v1/schema-spy
```

✅ HTTP 502 + `{ error: "fetch_failed" }`.

### 5. Plan gate

Switch the plan to Pro:

✅ HTTP 402 + `{ error: "tier_required", required_tier: "startup" }`.
