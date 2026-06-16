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
 *  - CRAWL:       the site-crawl pipeline (high volume, up to 600s/job)
 */
final class Queues
{
    public const INTERACTIVE = 'interactive';

    public const DEFAULT = 'default';

    public const SYNC = 'sync';

    public const CRAWL = 'crawl';
}
