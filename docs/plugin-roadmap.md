# EBQ SEO WordPress plugin — implementation matrix

This document tracks the blueprint in [plugin.txt](plugin.txt) against the shipped plugin in [`ebq-seo-wp`](../ebq-seo-wp/) and the EBQ Laravel API.

## Phases (delivered in repo)

| Phase | Scope | Key files |
|-------|--------|-----------|
| 1 — Static SEO | Title variables (`%%title%%`, `%%sep%%`, `%%sitename%%`), robots advanced UI, canonical in OG URL, taxonomy sitemaps, visible breadcrumbs + shortcode, settings for separator | [`class-ebq-title-template.php`](../ebq-seo-wp/includes/class-ebq-title-template.php), [`class-ebq-meta-output.php`](../ebq-seo-wp/includes/class-ebq-meta-output.php), [`class-ebq-social-output.php`](../ebq-seo-wp/includes/class-ebq-social-output.php), [`class-ebq-sitemap.php`](../ebq-seo-wp/includes/class-ebq-sitemap.php), [`class-ebq-breadcrumbs.php`](../ebq-seo-wp/includes/class-ebq-breadcrumbs.php), [`class-ebq-settings.php`](../ebq-seo-wp/includes/class-ebq-settings.php), [`class-ebq-seo-fields-meta-box.php`](../ebq-seo-wp/includes/class-ebq-seo-fields-meta-box.php) |
| 2 — Editor analysis | Debounced checks: keyphrase placement, density, word count, links, image alt | [`src/seo-panel/contentAnalysis.js`](../ebq-seo-wp/src/seo-panel/contentAnalysis.js), [`src/seo-panel/index.js`](../ebq-seo-wp/src/seo-panel/index.js) |
| 3 — Readability | Flesch (EN), long sentences, passive heuristic, transition words | [`src/seo-panel/readability.js`](../ebq-seo-wp/src/seo-panel/readability.js) |
| 4 — Indexables-lite | Post meta `_ebq_analysis_cache` on save | [`class-ebq-analysis-cache.php`](../ebq-seo-wp/includes/class-ebq-analysis-cache.php) |
| 5 — EBQ synergy | Insights API: `gsc.primary_query`, `indexing`, `audit.report_id`; sidebar: intent mismatch, audit links, custom-audit deep link | [`PluginInsightResolver.php`](../app/Services/PluginInsightResolver.php), [`src/sidebar/panel.jsx`](../ebq-seo-wp/src/sidebar/panel.jsx) |

## Non-goals (by design)

- No SERP crawling inside WordPress — rank tracking and competitor snapshots stay in EBQ.
- No duplicate heavy audit runner in PHP — use “Open audit in EBQ” and custom-audit URLs.

## Theme helpers

- `ebq_get_breadcrumbs_html( [ 'post_id' => 0, 'separator' => ' › ', 'class' => 'ebq-breadcrumbs' ] )` — registered in [`ebq-seo.php`](../ebq-seo-wp/ebq-seo.php).
- Shortcode: `[ebq_breadcrumbs]` (see [`class-ebq-breadcrumbs.php`](../ebq-seo-wp/includes/class-ebq-breadcrumbs.php)).

## Build

From `ebq-seo-wp/`: `npm install` then `npm run build` (webpack entries: `sidebar`, `seo-panel` via [`webpack.config.cjs`](../ebq-seo-wp/webpack.config.cjs)).

## Coexistence with Yoast / Rank Math / AIOSEO / TSF

Documented on **Settings → EBQ SEO**. When another SEO plugin is active, EBQ defers overlapping `<head>` output but keeps editor insights, redirects, and connect flows.
