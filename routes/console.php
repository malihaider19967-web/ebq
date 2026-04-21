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
