<?php

namespace App\Support;

/**
 * Named queues. Heavy/bulk work runs on its own queues + Supervisor worker pools
 * so latency-sensitive, user-is-waiting jobs are never starved behind a long
 * crawl or data sync. See /etc/supervisor/conf.d/ebq.conf for the worker pools.
 *
 *  - INTERACTIVE: user is actively waiting (guest tools, "check rank now", live audits)
 *  - DEFAULT:     emails, notifications, misc
 *  - SYNC:        scheduled/background bulk data syncs (GA/GSC/keywords)
 *  - CRAWL:       the site-crawl page-fetch pipeline (high volume, ≤300s/job) —
 *                 runs on the AUTOSCALED ephemeral worker boxes
 *  - CRAWL_FINALIZE: the long post-crawl analysis (AnalyzeSiteJob, ≤1200s) —
 *                 runs ONLY on the pinned permanent box so a scale-down drain can
 *                 never interrupt a finalize. See infra/crawler/autoscaling.md.
 */
final class Queues
{
    public const INTERACTIVE = 'interactive';

    public const DEFAULT = 'default';

    public const SYNC = 'sync';

    public const CRAWL = 'crawl';

    public const CRAWL_FINALIZE = 'crawl-finalize';
}
