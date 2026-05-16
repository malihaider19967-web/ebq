# MOAT feature smoke tests

One doc per MOAT-pulling feature shipped across Phases 1–3. Each follows
the same template:

1. **What the feature does** + which MOAT lever it pulls
   (computation lock-in / data gravity / network effect / AI-native).
2. **Files + endpoints + tables** involved.
3. **Pre-conditions** that must be true before the test will pass.
4. **Numbered scenarios** — happy path first, then edge cases + failure
   recovery. Each scenario has copy-paste commands and explicit
   acceptance criteria.
5. **Common failure modes** with the exact diagnostic command and fix.

Run these after every deploy that touches the relevant subsystem. They're
designed to be readable by a junior engineer with shell access to the
EBQ host (`/var/www/ebq/`) and the WP-CLI installed on a connected site.

## Index

### Phase 1 — Live score MOAT

- [01 — Live SEO score (rich factors)](01-live-seo-score.md)
- [02 — Keywords Everywhere backlinks sync](02-ke-backlinks-sync.md)
- [03 — Audit auto-queue + lite mode + re-audit on update](03-audit-queue.md)
- [04 — WP transient cache + LiteSpeed/CDN hardening](04-cache-hardening.md)
- [05 — Tier gating + reactive sync](05-tier-gating.md)

### Phase 2 — AI editor features

- [06 — AI title + meta rewrites](06-ai-snippet-rewrites.md)
- [07 — AI content brief](07-ai-content-brief.md)
- [08 — AI redirect matcher (404 capture → suggestions)](08-ai-redirect-matcher.md)

### Phase 3 — Network-effect features

- [09 — Live SERP-feature tracking](09-serp-feature-tracking.md)
- [10 — Cross-site benchmarks](10-cross-site-benchmarks.md)
- [11 — Backlink prospecting (persisted)](11-backlink-prospecting.md)
- [12 — Topical authority map](12-topical-authority-map.md)
- [13 — Entity coverage](13-entity-coverage.md)

## Conventions used in every doc

- `<WEBSITE_ID>` — the `websites.id` of the test site
- `<POST_ID>` — the `wp_posts.ID` of a post on the test site
- `<URL>` — the post's full canonical URL
- Tinker = `php artisan tinker` on the EBQ host
- WP-CLI commands run from the WP install root on the connected site
