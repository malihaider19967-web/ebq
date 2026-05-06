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

// Research section (Phase-2 pipelines + niche maintenance).
Schedule::command('ebq:research-enrich-new-keywords')->dailyAt('02:00');
Schedule::command('ebq:research-cluster-refresh')->weeklyOn(0, '03:00');
Schedule::command('ebq:niche-aggregates-recompute')->dailyAt('04:30');
Schedule::command('ebq:reclassify-niches')->monthlyOn(1, '04:00');
Schedule::command('ebq:discover-emerging-niches')->weeklyOn(1, '05:00');
Schedule::command('ebq:research-volatility-scan')->dailyAt('06:00');
Schedule::command('ebq:detect-research-signals')->dailyAt('07:45');
