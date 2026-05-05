<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GSC freshness lag (days)
    |--------------------------------------------------------------------------
    |
    | Google Search Console finalises daily numbers 24–72 hours after the day
    | ends. The automatic growth report (`ebq:send-reports`) snaps its
    | "current day" to the most recent date in `search_console_data` that's
    | ALSO at least this many days old, then compares it to the previous
    | period. Without this floor the email compared two partial days, which
    | almost always reads as a regression even when traffic was up.
    |
    | Tunable per-environment via REPORT_GSC_LAG_DAYS — set lower for sites
    | whose GSC catches up faster, higher when sync-stalls are common.
    | Floored at 1 day in the helper: today itself is always partial, so
    | a value of 0 still resolves to "yesterday at the earliest".
    |
    */
    'gsc_lag_days' => (int) env('REPORT_GSC_LAG_DAYS', 3),
];
