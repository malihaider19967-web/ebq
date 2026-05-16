# 15 — News sitemap + NewsArticle schema promotion

**MOAT lever:** Computation lock-in — we own the `/ebq-news-sitemap.xml`
endpoint and the auto-promotion of recent posts to `NewsArticle`.

## What the feature does

- Adds a Google News-shaped XML sitemap at `/ebq-news-sitemap.xml`
  listing every post published in the last 48 hours from the configured
  post types, with `<news:publication>` blocks.
- Hooks into the main sitemap index + robots.txt sitemap line.
- Auto-promotes Article schema to `NewsArticle` when the post type is
  included AND the post is ≤48h old.

## Files

- [`ebq-seo-wp/includes/class-ebq-news-sitemap.php`](../../ebq-seo-wp/includes/class-ebq-news-sitemap.php)
- [`ebq-seo-wp/includes/class-ebq-schema-output.php`](../../ebq-seo-wp/includes/class-ebq-schema-output.php) — `detect_type()`
- Plan flag: `plan_features.news_sitemap`

## Pre-conditions

- Plan has `news_sitemap` on (Startup+).
- The site has at least one post published in the last 48 hours.

## Scenarios

### 1. Sitemap availability

```bash
curl -i https://yoursite.test/ebq-news-sitemap.xml
```

✅ Expect HTTP 200, content-type `application/xml`, body contains
`<urlset xmlns:news=...>` plus a `<news:news>` entry per recent post.

### 2. Sitemap index registration

```bash
curl https://yoursite.test/ebq-sitemap.xml | grep ebq-news-sitemap
```

✅ The index references `ebq-news-sitemap.xml`.

### 3. robots.txt advertisement

```bash
curl https://yoursite.test/robots.txt | grep ebq-news-sitemap
```

✅ A `Sitemap: …/ebq-news-sitemap.xml` line is present.

### 4. NewsArticle schema promotion

Open a recent post (≤48h old) of an included post type. View source:

✅ The Article-typed JSON-LD node uses `"@type":"NewsArticle"`.

For older posts (>48h), the type falls back to `Article`.

### 5. Plan gate

Disable `news_sitemap` in `/admin/plans/<id>/edit`. Within ~5 minutes
(WP cache TTL) the WP plugin sees the flag flip:

```bash
curl -i https://yoursite.test/ebq-news-sitemap.xml
```

✅ Returns 404 (route un-registered).
