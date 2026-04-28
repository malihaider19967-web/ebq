# EBQ

Laravel application that connects to **Google Analytics 4** and **Google Search Console**, syncs metrics into the database, and surfaces them in a Livewire-powered dashboard (KPIs, keywords, pages, and site management). Email growth reports and scheduled syncs run through the queue.

## Requirements

- **PHP** 8.2+
- **Composer** 2.x
- **Node.js** 18+ (for Vite)
- **MySQL** (default database name in `.env.example` is `growthhub`; also used for the default **queue** and **cache** drivers)

## Quick start

1. **Clone and install dependencies**

   ```bash
   composer install
   npm install
   ```

2. **Environment**

   ```bash
   copy .env.example .env   # Windows
   # cp .env.example .env   # macOS / Linux
   php artisan key:generate
   ```

   Edit `.env` and set database, mail, and Google variables (see [Environment](#environment)).

3. **Database**

   Create the MySQL database (e.g. `growthhub`), then:

   ```bash
   php artisan migrate
   ```

4. **Frontend assets**

   ```bash
   npm run build
   ```

   For local development with hot reload, use `npm run dev` (often together with `php artisan serve`; see [Local development](#local-development)).

Alternatively, run the Composer setup script (installs PHP deps, creates `.env` if missing, generates key, migrates, installs npm, builds assets):

```bash
composer run setup
```

## Environment

| Variable | Purpose |
|----------|---------|
| `DB_*` | MySQL connection |
| `QUEUE_CONNECTION` | Example uses `database` (uses the `jobs` table after `php artisan migrate`) |
| `CACHE_STORE` | Example uses `database` (uses the `cache` / `cache_locks` tables after migrate) |
| `SESSION_DRIVER` | Example uses `database`; the `sessions` table is created by the bundled `0001_01_01_000000_create_users_table` migration |
| `MAIL_*` | Outgoing mail; queued growth reports use the configured mailer |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google OAuth (Socialite) |
| `GOOGLE_REDIRECT_URI` | Must match the redirect URI in Google Cloud Console (default: `${APP_URL}/auth/google/callback`) |
| `GOOGLE_CAP_AUDIENCE` | Expected CAP token audience (recommended: your OAuth client ID) |
| `GOOGLE_CAP_JWKS_URL` | Google JWK endpoint used to verify CAP token signatures |
| `GOOGLE_CAP_ISSUERS` | Comma-separated allowed CAP issuers |

Google OAuth is configured in `config/services.php` under `google` and uses these environment keys.

## Google Cloud setup

1. In [Google Cloud Console](https://console.cloud.google.com/), create an OAuth 2.0 **Web application** client.
2. Add the authorized redirect URI exactly as in `GOOGLE_REDIRECT_URI` (e.g. `http://localhost:8000/auth/google/callback` when using `php artisan serve` on port 8000).
3. Copy the client ID and secret into `.env`.
4. The app requests readonly scopes for Analytics and Search Console (`GoogleOAuthController`). Ensure the Google account you use has access to the GA4 properties and GSC properties you intend to connect.
5. For Cross-Account Protection (CAP), configure the receiver endpoint as `POST ${APP_URL}/auth/google/cap/events`.
6. Set `GOOGLE_CAP_AUDIENCE` to the expected audience configured in Google CAP (typically your OAuth client ID).

## Application flow

- **Authentication**: Routes under `routes/auth.php` serve login/register plus email verification. Email/password signups must verify email before onboarding and dashboard access. Google SSO users are marked verified automatically on successful sign-in.
- **Onboarding**: Until a user has at least one `Website` row, `EnsureOnboarded` redirects to `/onboarding` (except onboarding, Google OAuth, and settings routes). Users connect Google via `/auth/google/redirect` and complete site selection in the onboarding UI.
- **Main app** (after login + onboarded): Dashboard, keywords, pages, websites list, and settings (`routes/web.php`).

## Artisan commands

| Command | Description |
|---------|-------------|
| `php artisan ebq:sync-daily-data` | Refresh GA4 + GSC data for all websites. |
| `php artisan ebq:import-historical --days=480 [--website=ID]` | Backfill historical GA4 + GSC data (up to ~16 months). |
| `php artisan ebq:resync-gsc --days=30 [--website=ID]` | Re-sync Search Console with extended lookback (country/device backfill). |
| `php artisan ebq:send-reports` | Queue growth report emails to configured website recipients. |
| `php artisan ebq:detect-traffic-drops` | Dispatch anomaly detection jobs per website. |
| `php artisan ebq:track-rankings` | Dispatch SERP rank checks for due keywords. |
| `php artisan ebq:fetch-keyword-metrics [--website=ID] [--days=N]` | Queue Keywords Everywhere metric lookups for GSC queries. |
| `php artisan ebq:fetch-competitor-backlinks [--website=ID] [--audit=ID]` | Fetch/refresh competitor backlink datasets. |
| `php artisan ebq:auto-discover-prospects [--website=ID]` | Build outreach prospects from recent page audits. |
| `php artisan ebq:purge-empty-country-gsc [--website=ID] [--days=N]` | Remove legacy empty-country GSC rows. |
| `php artisan ebq:purge-sync-data [--website=ID] [--dry-run] [--force]` | Wipe sync-derived GA/GSC datasets for clean re-import. |
| `php artisan ebq:delete-website-data WEBSITE_ID [--dry-run] [--force]` | Delete a website and all rows related to it. |
| `php artisan ebq:package-plugin` | Build downloadable WordPress plugin zip in `public/downloads/`. |
| `php artisan ebq:publish-scheduled-plugin-releases` | Publish scheduled plugin releases. |
| `php artisan ebq:apply-plugin-version VERSION` | Update plugin version constants before packaging/release. |

These are registered on the scheduler in `routes/console.php`:

- `ebq:sync-daily-data` — daily
- `ebq:send-reports` — daily at 08:00

Run the scheduler locally with:

```bash
php artisan schedule:work
```

In production, add a single cron entry that runs `php artisan schedule:run` every minute.

## Queues

Sync jobs, AI insight generation, mail, and other queued work require a worker:

```bash
php artisan queue:work
```

For an all-in-one local experience (HTTP server, queue listener, logs, Vite), use:

```bash
composer run dev
```

## Testing

```bash
composer test
```

This clears config cache and runs `php artisan test` (PHPUnit).

## Tech stack

- Laravel 12
- Livewire 3
- Tailwind CSS + Vite
- Google API Client and Laravel Socialite for Google OAuth
- Database-backed queues and cache in the example environment (see `QUEUE_CONNECTION` / `CACHE_STORE`)

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
