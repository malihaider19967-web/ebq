# Mail, notifications & app wiring

How outbound email is transported, the catalog of mailables/notifications and what
triggers each, and the non-routing parts of app bootstrap (providers, scheduling,
observers, listeners, container bindings). Companion to
[configuration.md](./configuration.md). **No secret values — env NAMES only.**

---

## Mail transport — two layers

EBQ has a **global** mailer and a **per-client** override layer.

### Layer 1 — the global `postal` mailer

`MAIL_MAILER=postal` selects the custom mailer defined in `config/mail.php:56`. There is **no
custom transport class or service provider** — `postal` is just a standard Laravel `smtp`
transport pointed at the co-located self-hosted Postal server:

- `POSTAL_SMTP_HOST` (127.0.0.1) / `POSTAL_SMTP_PORT` (25) — local, so unaffected by the
  host's outbound port-25 block. Postal does external delivery, DKIM signing, bounce
  tracking (server "EBQ", domain `ebq.io`, SPF+DKIM verified).
- `POSTAL_SMTP_ENCRYPTION` → when `ssl`, the mailer uses the `smtps` scheme.
- `POSTAL_SMTP_USERNAME` / `POSTAL_SMTP_PASSWORD` **(secrets)**.
- Memory `email-delivery-postal`: SendGrid was silently dropping verification mail, which is
  why everything moved to Postal. **`MAIL_HOST=smtp.sendgrid.net` is stale** — it belongs to
  the unused `smtp` mailer, not `postal`.

Global sender: `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` (`mail.php:130`).

### Layer 2 — per-client report transports

Growth reports can be sent **from the client's own mailbox** (white-label). The picker lives
in `App\Services\Reports\ReportMailDispatcher::send()`:

- If no `MailTransport` is configured for the website owner → fall through to the global
  postal mailer via `Mail::to(...)->queue($mailable)`.
- `PROVIDER_SMTP` → a runtime-built SMTP mailer (`MailerFactory::buildSmtpMailer`).
- `PROVIDER_GMAIL` / `PROVIDER_OUTLOOK` → the mailable is rendered to a raw Symfony `Email`
  and handed to the OAuth send API (bypasses Laravel's mailer pipeline entirely;
  `buildSymfonyEmailFor`). Outlook/Gmail OAuth uses the `microsoft`/`google` services blocks.
- On send failure the transport's `last_error` is persisted; success marks it verified.

So **only `GrowthReportMail` participates in layer 2**; every other mailable uses the global
postal mailer.

---

## Mailables catalog  (`app/Mail/*` — 9 classes)

| Class | Trigger (file) | Recipient | Template | Queued? |
|---|---|---|---|---|
| `GrowthReportMail` | `ReportMailDispatcher::send` (driven by `ebq:send-reports`, daily 08:00) | website owner / report recipient | `emails.*` via `report()` + PDF attachment | queued (or per-client transport) |
| `CrawlReportMail` | admin `MarketingController::send` (`:91`, `->queue`) | client website owner | `emails.crawl-report` | ✅ `ShouldQueue` |
| `PageAuditReportMail` | `Livewire\Pages\PageAuditDetail` (`:58`) "email this audit" | user-entered address | `pages.partials.audit-report-export` | sync |
| `WebsiteAccessGrantedMail` | `Livewire\Websites\WebsiteTeam` (`:141`) on team-member add | granted member | markdown `mail.website-access-granted` | sync |
| `WebsiteTeamInvitationMail` | `Livewire\Websites\WebsiteTeam` (`:156`,`:216`) invite/resend | invitee email | markdown `mail.website-team-invitation` | sync |
| `GuestAuditLinkMail` | `Jobs\RunGuestPageAudit` (`:81`) | guest email (2nd free audit) | `emails.guest-audit-link` | from queued job |
| `GuestPageSpeedLinkMail` | `Jobs\RunGuestPageSpeedStrategy` (`:119`) | guest email | `emails.guest-pagespeed-link` | from queued job |
| `GuestKeywordVolumeLinkMail` | `Jobs\RunGuestKeywordVolume` (`:89`) | guest email | `emails.guest-volume-link` | from queued job |
| `GuestRankCheckLinkMail` | `Jobs\RunGuestRankCheck` (`:84`) | guest email | `emails.guest-rank-link` | from queued job |

The four **Guest\*LinkMail** classes share a pattern (memory `keyword-finder-limits` / guest
tools): sent on a visitor's *second* free use once they supply an email, each carrying a
`resultsUrl` + a `register` nudge. Subjects sanitize the host/keyword (`preg_replace` of
CR/LF/tab + length clamp) to prevent header injection from guest-supplied URLs.

### GrowthReportMail header → listener handshake

`GrowthReportMail` stamps an **`X-EBQ-Growth-Report-User-Id`** header on the message. The
`RecordGrowthReportSent` listener (below) reads it off the `MessageSent` event and writes
`users.last_growth_report_sent_at` — a delivery receipt decoupled from the mailable.

## Notifications  (`app/Notifications/*` — 1 class)

- **`TrafficDropAlert`** — dispatched by `Jobs\DetectTrafficDrops` (`:42`,
  `$user->notify(...)`), scheduled `ebq:detect-traffic-drops` daily 07:30. `via = ['mail']`.
  Builds a `MailMessage` listing each triggered metric (clicks / sessions / avg tracked-keyword
  position) with current-vs-baseline, % change and z-score, plus an "Open EBQ" action.
  `toArray` also persists a DB-notification payload. Mailed through the global postal mailer.

---

## App wiring — `bootstrap/app.php` (non-middleware)

(Middleware/routing covered by the routing reference.) The remaining concerns:

- **Routing registration** (`:9`) — `web`, `api`, `channels`, `console` route files; health
  endpoint `/up`; `then` closure also loads `routes/auth.php` under `web`.
- **Exception handling** (`:42`):
  - `Sentry\Laravel\Integration::handles($exceptions)` — forwards to Sentry, respects
    `config/sentry.php` ignore lists, no-op when DSN empty.
  - Custom render for **`QuotaExceededException`** — `402` JSON for API/JSON callers (WP
    plugin shows a banner); browser flows flash `quota_notice` and redirect back to billing.
- **CSRF exemptions** (`:25`) — three server-to-server POST paths that can't carry a CSRF
  token: `auth/google/cap/events` (Google CAP/RISC), `stripe/webhook` (Cashier verifies the
  Stripe signature), `webhooks/keyword-finder` (HMAC-verified body; path =
  `KEYWORD_FINDER_WEBHOOK_PATH`).
- **Console / scheduling** is **not** in `bootstrap/app.php` — it lives in
  `routes/console.php` (see below).

## Scheduling  (`routes/console.php`)

Driven by the `ebq-schedule` supervisor program (`schedule:work`) on Box A.

| Command | Cadence | Purpose |
|---|---|---|
| `ebq:sync-daily-data` | daily | GSC/GA sync (queue `sync`, Box B) |
| `ebq:detect-traffic-drops` | 07:30 | → `TrafficDropAlert` |
| `ebq:send-reports` | 08:00 | → `GrowthReportMail` (uses `REPORT_GSC_LAG_DAYS`) |
| `ebq:track-rankings` | hourly | rank tracking |
| `ebq:auto-discover-prospects` | 03:30 | backlink prospect discovery (KE-safe, freshness-gated) |
| `ebq:publish-scheduled-plugin-releases` | every minute | WP plugin release publishing |
| `ebq:crawl-websites` | weekly Mon 02:00 | full recrawl (conditional-GET cheap) |
| `ebq:crawl-websites --sitemap-deltas` | 04:30 | daily new-sitemap-URL crawl |
| `ebq:check-keyword-servers` | every 5 min | keyword-API fleet health snapshot |

## Service providers  (`app/Providers/*` — 1 file)

`AppServiceProvider` is the only provider. Notable wiring:

**`register()`:**
- `require app/Support/helpers.php` (global helper functions).
- `singleton(LanguageDetectorService)`.
- **`bind(LlmClient → MistralClient)`** — the platform-wide LLM binding. The model is
  resolved through `AiModelConfig::currentModel()` so the admin's persisted dropdown choice
  (`settings.ai.llm.model`) overrides the `MISTRAL_MODEL` config default; per-call `model`
  overrides still win. Key = `services.mistral.key`.
- Cashier note: Cashier 16+ migrations are manually copied into `database/migrations/`; no
  migration-skip API call needed.

**`boot()`:**
- `Gate::policy(Website::class, WebsitePolicy::class)`.
- **`RankTrackingKeyword::observe(RankTrackingKeywordObserver::class)`** (see below).
- **`Event::listen(MessageSent::class, RecordGrowthReportSent::class)`** — the growth-report
  delivery receipt.
- `Event::listen(SocialiteWasCalled → MicrosoftExtendSocialite@handle)` — registers the
  Microsoft Socialite driver. ⚠️ Must be the `Class@method` **string** form; the array form
  breaks `package:discover` ("Undefined array key 1").
- `RateLimiter::for('oauth', ...)` — 20/min per user-or-IP.
- Cashier billable model is `User` (per-user billing migration moved Cashier columns off
  `websites`); no explicit `useCustomerModel()`.

## Observer  (`app/Observers/RankTrackingKeywordObserver.php`)

Observes `RankTrackingKeyword`:
- **`creating`** — enforces the per-plan active-keyword cap as **defense-in-depth**. Resolves
  the billed user (website owner, else `user_id`), checks `UsageMeter::rankTrackerCap`, and
  throws `QuotaExceededException` if at the limit. The Livewire UI already blocks at the form
  level; this catches API-route additions (Plugin HQ, future integrations) that bypass it.
- **`created`** — dispatches `FetchKeywordMetricsJob([keyword], 'global')` on the
  `INTERACTIVE` queue so volume/CPC/competition populate on first render.

## Listener  (`app/Listeners/RecordGrowthReportSent.php`)

Handles `MessageSent`. If the message carries `X-EBQ-Growth-Report-User-Id`, parses the user
ID and updates `users.last_growth_report_sent_at = now()`. No-op for any other mail. This is
how "last report sent" is tracked without coupling it into `GrowthReportMail` itself.

## Key container bindings / registries

| Binding | Where | Notes |
|---|---|---|
| `LlmClient` → `MistralClient` | `AppServiceProvider::register` | platform LLM; model via `AiModelConfig` |
| `LanguageDetectorService` (singleton) | `AppServiceProvider::register` | language detection |
| `AiToolRegistry` | `app/Services/AiToolRegistry.php` | **code-defined**, no DB. A `const TOOL_CLASSES` list (Research/Writing/… categories); instantiated lazily via the container so tools can request constructor deps. Injected into `AiToolRunner`. Add a tool = append a class line; missing classes fail fast at boot |
| `ProxyPool` | `app/Services/Crawler/ProxyPool.php` | crawler anti-blocking pool; sources = `proxies` table + `proxylist.txt`, gated by `crawler.proxy.*` |
| `WebsitePolicy` | `Gate::policy(Website::class, ...)` | authorization for website resources |

`AiToolRegistry` is **not** a container singleton binding — it's a plain class resolved via
constructor injection; its *contents* are the code-defined `TOOL_CLASSES` list (explicit, not
filesystem-discovered, so the canonical tool order is reviewable and feature-flagging a tool
is a one-line deletion).
