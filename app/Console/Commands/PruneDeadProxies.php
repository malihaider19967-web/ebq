<?php

namespace App\Console\Commands;

use App\Models\Proxy;
use App\Services\Crawler\ProxyPool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Continuously health-checks every already-tracked proxy and deletes the
 * ones that fail right now — unlike `ebq:proxy-list-refresh` (import-only,
 * never touches existing rows), this command's whole job IS the deletion.
 * Always scheduled (not gated by `CRAWLER_PROXY_AUTO_IMPORT` — that flag only
 * controls whether NEW candidates get imported, not whether dead ones get
 * swept), so the pool stays clean even with auto-import off.
 *
 * No fail_count/threshold here on purpose: each run is a fresh, independent
 * test, so one bad result is enough to remove it (same semantics as the
 * admin "Retest all" button's deleteOnFail path).
 */
class PruneDeadProxies extends Command
{
    protected $signature = 'ebq:proxy-pool-prune {--concurrency=25} {--timeout=6}';

    protected $description = 'Live-test every tracked proxy and delete the ones that fail.';

    public function handle(ProxyPool $pool): int
    {
        $concurrency = max(1, (int) $this->option('concurrency'));
        $timeout = max(1, (int) $this->option('timeout'));

        $proxies = Proxy::all(['id', 'url']);
        if ($proxies->isEmpty()) {
            $this->info('No tracked proxies to check.');

            return self::SUCCESS;
        }

        $byUrl = $proxies->keyBy('url');
        $results = $pool->testBatch($proxies->pluck('url')->all(), $concurrency, $timeout);

        $kept = $removed = 0;
        foreach ($results as $url => $ok) {
            $proxy = $byUrl->get($url);
            if (! $proxy) {
                continue;
            }
            if ($ok) {
                $kept++;
                $proxy->update(['fail_count' => 0, 'success_count' => $proxy->success_count + 1, 'last_used_at' => now(), 'last_ok_at' => now()]);
            } else {
                $removed++;
                $proxy->delete();
            }
        }

        if ($removed > 0) {
            Cache::forget('crawler:proxypool:urls');
        }

        $this->info("Done: {$kept} kept, {$removed} removed.");

        return self::SUCCESS;
    }
}
