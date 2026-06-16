# Routing — consolidated endpoint map

> Cross-cutting reference for every route the EBQ Laravel app exposes. For the
> middleware/guard/authz internals behind these routes see
> [http-and-auth.md](./http-and-auth.md).

## Where routes are registered

`bootstrap/app.php:9` (`withRouting`) wires four route files plus a health check:

| File | Group applied | Notes |
|---|---|---|
| `routes/web.php` (350 ln) | `web` (session, CSRF, cookies) | public + app + admin + OAuth + WP + billing |
| `routes/api.php` | **no global group** — each route names its own middleware | plugin API, `v1` prefix |
| `routes/auth.php` | `web` (added via `then:` closure, `bootstrap/app.php:16`) | login/register/verify/logout + Google SSO |
| `routes/channels.php` | — | **empty** (no broadcast channels) |
| `routes/console.php` | — | schedule only — see [deployment-and-queues.md](../deployment-and-queues.md), not re-documented here |
| `health: '/up'` | — | Laravel's built-in health endpoint |

Note: `routes/api.php` is **not** registered with a `prefix('api')`. The `v1`
prefix on the route group means paths are literally `/v1/...` (plus the public
`/api/v1/plans` which lives in `web.php` with the `api/` prefix written out).

---

## 1. Public / marketing (`web`, anonymous)

Static marketing pages via `Route::view` — `web.php:30-39`.

| Path | Name | Target |
|---|---|---|
| `/` | `landing` | `landing` view |
| `/features` | `features` | view |
| `/wordpress-plugin` | `wordpress-plugin` | view |
| `/pricing` | `pricing` | view (reads `Plan` list; `FREE` promo swaps grid) |
| `/website-revamp`, `/contact`, `/guide` | resp. | views |
| `/terms-conditions`, `/privacy-policy`, `/refund-policy` | resp. | `legal.*` views |
| `/up` | — | health check |

---

## 2. Guest lead-gen tools (`web`, anonymous, rate-limit + reCAPTCHA in controller)

Four no-signup tools, all sharing the same progressive-friction pattern
(`web.php:41-76`). Each is: a `Route::view` landing page + `store` (POST, queues a
job) + `status` (poll) + `show` (report). **Auth/throttle/reCAPTCHA are enforced in
the controllers, not via route middleware** — see [http-and-auth.md](./http-and-auth.md#guest-tools-friction).

| Tool | View path | POST / status / show | Controller |
|---|---|---|---|
| SEO audit | `/free-audit`, hero on `/` | `/audit`, `/audit/{guestPageAudit}/status`, `/audit/{guestPageAudit}` | `GuestAuditController` |
| PageSpeed | `/pagespeed-test` | `/pagespeed-test` (+ `/status`, `/{guestPageSpeed}`) | `GuestPageSpeedController` |
| Rank tracker | `/rank-tracker` | `/rank-tracker` (+ `/status`, `/{guestRankCheck}`) | `GuestRankCheckController` |
| Keyword volume | `/keyword-volume-checker` | same shape, `{guestKeywordVolume}` | `GuestKeywordVolumeController` |

WHY the distinct public paths (`/pagespeed-test` vs the authed `/pagespeed`,
`/keyword-volume-checker` vs authed `/keyword-volume`): avoid colliding with the
in-app portal tools (`web.php:72`). Detailed flow in [guest-tools/](../guest-tools/).

---

## 3. Authenticated app (`web` + `auth` + `verified` + `onboarded`)

The main dashboard group — `web.php:146-245`. **Every** route additionally carries a
`feature:<key>` middleware that gates per-team-member access (see
[http-and-auth.md](./http-and-auth.md#feature-gating) and
[billing/plans-and-gating.md](../billing/plans-and-gating.md)). Most are `Route::view`
rendering a Livewire-backed Blade page.

| Path | Name | `feature:` | Target |
|---|---|---|---|
| `/dashboard` | `dashboard` | dashboard | `dashboard` view |
| `/issues/{key}` | `issues.show` | dashboard | view (`key` = `[a-z0-9_]+`) |
| `/statistics` | `statistics` | dashboard | view |
| `/keywords` | `keywords.index` | keywords | view |
| `/keywords/fix` | `keywords.fix` | audits | view (registered **before** the `{query}` catch-all) |
| `/keywords/{query}` | `keywords.show` | keywords | view (`query` = `.*`) |
| `/keyword-research` | `keyword-research.index` | keywords | unified hub (Ideas·Volume·Gap) |
| `/keyword-volume` → `?tab=volume` | `keyword-volume.index` | — | **redirect** to hub |
| `/keyword-ideas` → `?tab=ideas` | `keyword-ideas.index` | — | redirect |
| `/competitive` → `?tab=gap` | `competitive.index` | — | redirect |
| `/competitive/competitors` | `competitive.competitors` | keywords | view |
| `/rank-tracking` | `rank-tracking.index` | rank_tracking | view |
| `/rank-tracking/{keywordId}` | `rank-tracking.show` | rank_tracking | view (`whereNumber`) |
| `/backlinks` | `backlinks.index` | backlinks | view |
| `/pages` | `pages.index` | pages | view |
| `/pages/{id}` | `pages.show` | pages | view (`id` = `.*`) |
| `/custom-audit` | `custom-audit.index` | audits | view |
| `/pagespeed` | `pagespeed.index` | audits | view |
| `/sitemaps` | `sitemaps.index` | sitemaps | view |
| `/link-structure` | `link-structure.index` | link_structure | view |
| `/page-audits/{pageAuditReport}` | `page-audits.show` | audits | `PageAuditController@show` |
| `/page-audits/{id}/download` | `page-audits.download` | audits + `throttle:30,1` | `PageAuditController@download` |
| `/websites` | `websites.index` | *(none — anyone with ≥1 site)* | view |
| `/team` | `team.index` | team | view |
| `/reports` | `reports.index` | reports | view |
| `/settings` | `settings.index` | settings | view |

### AI Studio sub-group (`feature:ai_studio`) — `web.php:194-244`

| Path | Name | Extra | Target |
|---|---|---|---|
| `/ai-studio` | `ai-studio.index` | — | `AiStudioController@index` |
| `/ai-studio/tools/{toolId}/run` | `ai-studio.run` | `throttle:30,1` | `AiStudioController@run` |
| `/ai-studio/brand-voice` (PUT/DELETE) | `ai-studio.brand-voice.*` | PUT `throttle:10,1` | `AiStudioController` |
| `/ai-studio/blog-post-wizard` | `ai-studio.wizard` | — | `AiStudioWriterController@page` |
| `/ai-studio/writer-projects/*` | `ai-studio.writer-projects.*` | REST + brief/strategy/generate/credits | `AiStudioWriterController` |
| `/ai-studio/ai-writer-prompts` (GET/POST/DELETE) | `ai-studio.prompts.*` | POST `throttle:20,1` | `AiStudioWriterController` |

### OAuth / onboarding sub-group (`auth` + `verified` + `throttle:oauth`) — `web.php:247-257`

Note: this group is **not** `onboarded`-gated (you reach it before having a website).

| Path | Name | Target |
|---|---|---|
| `/onboarding` | `onboarding` | view |
| `/auth/google/redirect` · `/auth/google/callback` | `google.redirect` · `google.callback` | `GoogleOAuthController` (Analytics/GSC/Indexing scopes) |
| `/auth/google/mail/redirect` | `google.mail.redirect` | incremental `gmail.send` consent |
| `/auth/microsoft/redirect` · `/auth/microsoft/callback` | `microsoft.*` | `MicrosoftOAuthController` (Outlook send transport) |

### Billing (`web` + `auth`) — `web.php:104-129`

Auth required to resolve which `Website`/user is billed. `BillingController`.

| Path | Name | Method |
|---|---|---|
| `/billing` | `billing.show` | show |
| `/billing/swap` (POST) | `billing.swap` | swap (preferred for active subs) |
| `/billing/cancel` · `/billing/resume` (POST) | `billing.cancel-subscription` · `billing.resume` | |
| `/billing/checkout` | `billing.checkout` | mints Stripe Hosted Checkout, redirects |
| `/billing/success` · `/billing/cancel-checkout` | `billing.success` · `billing.cancel-checkout` | post-checkout return |
| `/billing/portal` | `billing.portal` | Stripe Customer Portal redirect |

See [billing/README.md](../billing/README.md). Note `billing.cancel-checkout` was
renamed off `billing.cancel` so the in-app cancel POST could claim that name.

---

## 4. Admin (`web` + `auth` + `admin`, prefix `/admin`, name `admin.`)

`web.php:264-348`. `admin` = `EnsureAdmin` → 403 unless `user.is_admin`. See
[admin/](../admin/).

| Area | Routes (name suffix) |
|---|---|
| Clients | `clients.index/store/bulk/update`, `clients.crawl`, `clients.impersonate` |
| Impersonation stop | `admin.impersonation.stop` (POST, **outside** the group — only `auth`, `web.php:350`) |
| Activities / Leads / Usage | `activities.index`, `leads.index`, `usage.index` |
| Crawler / Proxies / Docs | `crawler.index`, `proxies.index`, `docs.crawler` |
| Marketing | `marketing.index`, `marketing.sends`, `marketing.send` |
| Plugin releases | `plugin-releases.*` (index/store/publish/upload-zip/rollback/destroy/toggle-updates), `plugin-adoption.index` |
| Website features | `website-features.index`, `website-features.global-update` (master kill-switch), `website-features.update` (`{website}`) |
| Billing (read-only) | `billing.index` |
| Plans | `plans.index/create/store/edit/update` (drives `/pricing` + `/api/v1/plans`) |
| Keyword API fleet | `keyword-servers.*` (index/store/update/destroy + test/test-keyword/test-website) |
| Artisan / Settings | `commands.index`, `settings` (edit/update/refresh-models) |

WHY `website-features.global` is a literal path before `{website}`: route-model
binding would otherwise claim the segment `"global"` (`web.php:301-307`).

---

## 5. API v1 — WordPress plugin (`routes/api.php`)

Bearer-token (Sanctum, tokenable = `Website`) endpoints. **No `web` group**, so no
session/CSRF. Stack on every route (`api.php:18`):
`website.api:read:insights` → `website.features` → `throttle:60,1`. Full auth detail
+ the `hq` surface in [wordpress-plugin/hq-api.md](../wordpress-plugin/hq-api.md);
auth internals in [http-and-auth.md](./http-and-auth.md#plugin-api-sanctum).

| Group | Prefix | Controller | What |
|---|---|---|---|
| Post insights | `/v1/posts/{externalPostId}/*` | `PluginInsightsController` | seo-score, serp-preview, internal/related/focus keywords, content-brief, ai-writer/plan, ai-block, chat, research, entity-coverage, topical-gaps, rewrite-snippet |
| Posts/redirects/dashboard | `/v1/posts`, `/v1/redirect-suggestions/*`, `/v1/dashboard`, `/v1/reports/iframe-url`, `/v1/plugin/heartbeat`, `/v1/website-features` | `PluginInsightsController`, `PluginHeartbeatController` | passive sync + feature map |
| EBQ HQ | `/v1/hq/*` | `PluginHqController` | overview, performance, keywords (CRUD + recheck + history + candidates), gsc-keywords, pages, index-status, insights, growth-report, page-audit(s), serp-features, backlink/outreach prospects, benchmarks, topical-authority |
| HQ AI Writer | `/v1/hq/writer-projects/*`, `/v1/hq/ai-writer-prompts/*` | `WriterProjectController`, `AiWriterPromptController` | project lifecycle, prompt library |
| HQ AI Studio | `/v1/hq/ai/tools/*`, `/v1/hq/ai/brand-voice` | `AiToolController` | registry-driven tool catalog |

Public API (anonymous, in `web.php`, no token):

| Path | Name | Target |
|---|---|---|
| `/api/v1/plans` | `api.v1.plans` | `Api\V1\PricingController@public` (drives plugin setup wizard) |

---

## 6. Webhooks (CSRF-exempt — `bootstrap/app.php:25`)

Server-to-server; no session auth. Each verifies a signature internally.

| Path | Name | Auth | Target |
|---|---|---|---|
| `/stripe/webhook` (POST) | `cashier.webhook` | Stripe signature via `STRIPE_WEBHOOK_SECRET` | `StripeWebhookController` (extends Cashier) |
| `/webhooks/keyword-finder` (POST) | `webhooks.keyword-finder` | HMAC body sig vs server `webhook_secret` | `Webhooks\KeywordFinderWebhookController` |
| `/auth/google/cap/events` (POST) | `google.cap.events` | Google RISC/CAP token (in controller); `throttle:oauth` | `GoogleCapController` |

CAP is CSRF-exempt **and** throttled; the other two are CSRF-exempt only.

---

## 7. Google / Microsoft OAuth

| Flow | Path | Group | Controller method |
|---|---|---|---|
| Google **SSO** (login/register) | `/auth/google/sso`, `/auth/google/sso/callback` | `web` + `guest` + `throttle:oauth` (`auth.php:11`) | `GoogleOAuthController::ssoRedirect/ssoCallback` |
| Google **data** connect | `/auth/google/redirect`, `/auth/google/callback` | `auth` + `verified` + `throttle:oauth` | `redirect/callback` |
| Google mail scope | `/auth/google/mail/redirect` | same | `redirectMailScope` |
| Microsoft Outlook | `/auth/microsoft/redirect`, `/auth/microsoft/callback` | same | `MicrosoftOAuthController` |
| Google CAP receiver | `/auth/google/cap/events` | webhook (§6) | `GoogleCapController` |

WHY two Google flows: SSO is `guest`-only (sign-in), the data connect is for an
already-authenticated user granting Analytics/GSC/Indexing scopes.

---

## 8. WordPress connect / embed / download

| Path | Name | Middleware | Target |
|---|---|---|---|
| `/wordpress/connect` (GET start, POST approve) | `wordpress.connect.start/approve` | `web` + `auth` | `WordPressConnectController` — POST mints the scoped `read:insights` Sanctum token (`:77`) |
| `/wordpress/embed/reports` | `wordpress.embed.reports` | `web` + `signed` | `WordPressEmbedController@reports` (signed deep-link, session auth) |
| `/wordpress/embed/page-audit` | `wordpress.embed.page-audit` | `web` + `signed` | `WordPressEmbedController@pageAudit` |
| `/wordpress/plugin.zip` | `wordpress.plugin.download` | anonymous | `WordPressPluginDownloadController` (bypasses `public/` cache) |
| `/wordpress/plugin/version` | `wordpress.plugin.version` | anonymous | `WordPressPluginVersionController` |

See [wordpress-plugin/README.md](../wordpress-plugin/README.md) and
[wordpress-plugin/releases.md](../wordpress-plugin/releases.md).

---

## 9. Auth scaffolding (`routes/auth.php`, all `web`)

`guest` group: `login` (GET/POST), `register` (GET/POST), Google SSO (§7).
`auth` group: `verification.notice`, `verification.verify` (`signed`+`throttle:6,1`),
`verification.send` (`throttle:6,1`), `logout`. Login + register run reCAPTCHA when
configured and a per-key login throttle (`LoginRequest`). Detail in
[http-and-auth.md](./http-and-auth.md#login-throttle).

---

## Gotchas

- **`channels.php` is empty** — no Laravel Echo / broadcast wiring; live UI is
  Livewire polling.
- **`api.php` has no `web` group** → no CSRF/session on the plugin API; auth is the
  bearer token only.
- **Catch-all ordering**: `/keywords/fix` and `/keyword-research` redirects are
  declared before `/keywords/{query}` (`.*`) so they aren't swallowed.
- **`admin.impersonation.stop`** is deliberately outside the `admin` group (only
  `auth`) — the impersonated session is not an admin, so it must still be able to
  stop (`web.php:350`).
