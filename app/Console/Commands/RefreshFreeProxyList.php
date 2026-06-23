<?php

namespace App\Console\Commands;

use App\Models\Proxy;
use App\Services\Crawler\ProxyPool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Pulls candidate proxies from free public proxy-list repos (currently
 * iplocate/free-proxy-list + proxifly/free-proxy-list, both updated every
 * few minutes upstream) and live-tests each one before it ever enters the
 * pool — free lists are mostly dead or stale by fetch time, and a small
 * fraction actively MITM HTTPS (self-signed cert swap), so untested import
 * is not safe. Only a real HTTPS round-trip (cert verification ON, catches
 * MITM, via `ProxyPool::testBatch()`) earns a spot.
 *
 * Import-only — this command never touches already-tracked rows. Ongoing
 * health-checking + deletion of dead proxies is `ebq:proxy-pool-prune`'s job
 * (always on); real-usage failures also delete via `ProxyPool::markFailure()`.
 * Scheduling this command is gated OFF by default (`CRAWLER_PROXY_AUTO_IMPORT`)
 * — it can always be run manually (artisan, or the admin /admin/proxies
 * "Import now" button) regardless of that flag.
 *
 * Source format is one `scheme://host:port` per line — already what
 * ProxyPool::normalise() expects, no parsing needed beyond a trim. Both
 * default sources ship in that exact format.
 */
class RefreshFreeProxyList extends Command
{
    private const DEFAULT_SOURCES = [
        'https://raw.githubusercontent.com/iplocate/free-proxy-list/main/all-proxies.txt',
        'https://raw.githubusercontent.com/proxifly/free-proxy-list/main/proxies/all/data.txt',
    ];

    protected $signature = 'ebq:proxy-list-refresh
        {--source=* : Proxy list URL(s), repeatable. Defaults to the built-in iplocate + proxifly lists if omitted}
        {--limit=300 : Max new candidates to test this run (sources have ~thousands of lines; bound runtime)}
        {--concurrency=25}
        {--timeout=6 : Per-proxy test timeout in seconds}';

    protected $description = 'Fetch + live-test candidate proxies from free public lists and add the ones that pass to the crawler proxy pool.';

    public function handle(ProxyPool $pool): int
    {
        $sources = $this->option('source') ?: self::DEFAULT_SOURCES;
        $limit = max(1, (int) $this->option('limit'));
        $concurrency = max(1, (int) $this->option('concurrency'));
        $timeout = max(1, (int) $this->option('timeout'));

        $fromSource = [];
        foreach ($sources as $source) {
            try {
                $body = Http::timeout(15)->get($source)->throw()->body();
            } catch (\Throwable $e) {
                $this->warn("Could not fetch {$source}: {$e->getMessage()} — skipping this source.");

                continue;
            }

            foreach (preg_split('/[\r\n]+/', $body) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $url = $pool->normalise($line);
                if ($url !== null) {
                    $fromSource[$url] = true;
                }
            }
        }

        if ($fromSource === []) {
            $this->error('All sources failed or returned no parseable lines — nothing to import.');

            return self::FAILURE;
        }

        $existing = array_fill_keys(Proxy::pluck('url')->all(), true);
        $candidates = array_values(array_diff(array_keys($fromSource), array_keys($existing)));
        shuffle($candidates);
        $candidates = array_slice($candidates, 0, $limit);

        if ($candidates === []) {
            $this->info('No new candidates outside the already-tracked pool.');

            return self::SUCCESS;
        }

        $this->info('Testing '.count($candidates)." new candidate proxies (concurrency={$concurrency}, timeout={$timeout}s)...");

        $results = $pool->testBatch($candidates, $concurrency, $timeout);
        $passed = 0;
        foreach ($results as $url => $ok) {
            if ($ok) {
                $passed++;
                Proxy::updateOrCreate(
                    ['url_hash' => Proxy::hashUrl($url)],
                    ['url' => $url, 'label' => 'free-proxy-list (auto)', 'active' => true, 'fail_count' => 0],
                );
            }
        }

        if ($passed > 0) {
            Cache::forget('crawler:proxypool:urls');
        }

        $this->info("Done: {$passed} passed and added, ".(count($candidates) - $passed).' failed and skipped.');

        return self::SUCCESS;
    }
}
