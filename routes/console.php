<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ebq:sync-daily-data')->daily();
Schedule::command('ebq:detect-traffic-drops')->dailyAt('07:30');
Schedule::command('ebq:send-reports')->dailyAt('08:00');
Schedule::command('ebq:track-rankings')->hourly();
// Nightly auto-discovery of backlink prospects from each website's recent
// page audits. Idempotent + freshness-gated, so re-runs are KE-safe.
Schedule::command('ebq:auto-discover-prospects')->dailyAt('03:30');
Schedule::command('ebq:publish-scheduled-plugin-releases')->everyMinute();

// Site crawler. Weekly full recrawl (conditional-GET + content-hash keep it
// cheap — every URL is re-verified, unchanged pages cost a 304/no re-parse). A
// daily sitemap-delta check crawls brand-new sitemap URLs within a day instead
// of waiting for the weekly pass. One-off backfill of existing never-crawled
// sites is run manually after deploy: `php artisan ebq:crawl-websites --backfill`.
Schedule::command('ebq:crawl-websites')->weeklyOn(1, '02:00');
Schedule::command('ebq:crawl-websites --sitemap-deltas')->dailyAt('04:30');

// Keep the self-hosted keyword API fleet's health/queue snapshot warm so the
// load balancer routes to live, least-busy servers.
Schedule::command('ebq:check-keyword-servers')->everyFiveMinutes();
