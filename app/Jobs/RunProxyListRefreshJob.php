<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs `ebq:proxy-list-refresh` in the background — fired by the admin
 * /admin/proxies "Import now" button (the scheduled auto-import is OFF by
 * default, gated by `CRAWLER_PROXY_AUTO_IMPORT`; this is the manual trigger).
 * Queued because a full run (fetch + concurrent live-test of every tracked +
 * sampled-new proxy) can take well over a normal request timeout.
 */
class RunProxyListRefreshJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(\App\Support\Queues::DEFAULT);
    }

    public function handle(): void
    {
        Artisan::call('ebq:proxy-list-refresh');
    }
}
