=== EBQ SEO ===
Contributors: ebq
Tags: seo, search console, analytics, rank tracking, core web vitals
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Surfaces EBQ's cross-signal SEO insights (cannibalization, striking distance, rank tracking, page audits) inside the Gutenberg editor.

== Description ==

EBQ SEO connects your WordPress site to your EBQ workspace so content editors see:

* Per-post rank, 30d clicks, striking-distance flag, and cannibalization warnings in a Gutenberg sidebar.
* 30d clicks + avg position columns in the posts list.
* Counts of cannibalizations, striking-distance keywords, indexing failures with live traffic, and content-decay pages in the WordPress dashboard.

Requires an active EBQ workspace. Create yours at https://app.ebq.io.

== Installation ==

1. Upload the `ebq-seo` folder to `/wp-content/plugins/`.
2. Activate through **Plugins** in WordPress.
3. Go to **Settings → EBQ SEO**.
4. In EBQ, open **Settings → Integrations → WordPress plugin** and generate a verification code.
5. Paste the code into the plugin settings, save, then click Verify in EBQ.
6. Paste the issued API token back into the plugin settings and save.

== Changelog ==

= 1.0.0 =
* Initial release: verification flow, Gutenberg sidebar, admin column, dashboard widget.
