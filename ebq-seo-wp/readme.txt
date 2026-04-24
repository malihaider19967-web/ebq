=== EBQ SEO ===
Contributors: ebq
Tags: seo, search console, analytics, rank tracking, core web vitals
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 2.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Surfaces EBQ's cross-signal SEO insights (cannibalization, striking distance, rank tracking, page audits) inside the Gutenberg editor.

== Description ==

EBQ SEO connects your WordPress site to your EBQ workspace so content editors see:

* Per-post rank, 30d clicks, striking-distance flag, and cannibalization warnings in a Gutenberg sidebar.
* 30d clicks + avg position columns in the posts list.
* Counts of cannibalizations, striking-distance keywords, indexing failures with live traffic, and content-decay pages in the WordPress dashboard.

One-click connect — no copying of codes or tokens. Requires an EBQ account. Create yours at https://ebq.io.

== Installation ==

1. Upload the `ebq-seo` folder to `/wp-content/plugins/` (or use *Plugins → Add New → Upload*).
2. Activate through **Plugins** in WordPress.
3. Go to **Settings → EBQ SEO** and click **Connect to EBQ**.
4. Log in to EBQ, pick which website to link, approve.
5. You'll bounce back to WordPress with the connection live.

**Self-hosted EBQ:** add `define('EBQ_API_BASE', 'https://your-ebq-host');` to `wp-config.php` before activating.

== Changelog ==

= 2.2.1 =
* Version bump.

= 2.2.0 =
* Yoast-style title variables (%%title%%, %%sep%%, %%sitename%%), taxonomy XML sitemaps, visible breadcrumbs + shortcode, analysis cache on save.
* Gutenberg: content analysis checklist, readability panel, advanced robots field, SERP preview uses resolved titles.
* EBQ API: primary GSC query, URL Inspection summary, audit report deep links; sidebar actions for new audit and full report.

= 1.0.0 =
* Initial release: one-click OAuth-style connect, Gutenberg sidebar, admin column, dashboard widget.
