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
