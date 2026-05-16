# 25 — LLMs.txt endpoint

## What the feature does

Publishes a Markdown document at `/llms.txt` summarising the site so
LLM crawlers (and humans) can discover the freshest pages without
HTML scraping.

## Files

- [`ebq-seo-wp/includes/class-ebq-llms-txt.php`](../../ebq-seo-wp/includes/class-ebq-llms-txt.php)
- Plan flag: `plan_features.llms_txt`

## Pre-conditions

- Plan has `llms_txt` on (Free+, default ON).
- Permalinks flushed at least once after install.

## Scenarios

### 1. Endpoint reachable

```bash
curl -i https://yoursite.test/llms.txt
```

✅ HTTP 200, content-type `text/markdown; charset=UTF-8`. Body starts
with `# <Site title>` followed by an `> ` description blockquote and a
`## Latest content` section listing the most recent posts/pages.

### 2. Settings round-trip

EBQ HQ → SEO settings → "LLMs.txt" panel: change the description,
toggle off `page` post type, save.

```bash
curl -s https://yoursite.test/llms.txt | grep -c '/sample-page/'
```

✅ Pages no longer listed; description block reflects the new copy.

### 3. Cache invalidation

Edit any post and click Update.

✅ The next request to `/llms.txt` reflects the change within 60s
(cache key flushed on `transition_post_status` per the module).

### 4. Plan gate

Switch the plan to a tier without `llms_txt` (we don't ship one in
the canonical seed; fabricate one in tinker if needed).

✅ `/llms.txt` returns 404.
