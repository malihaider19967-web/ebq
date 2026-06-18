<?php

namespace App\Console\Commands;

use App\Models\CrawlSite;
use App\Services\Crawler\DomainRateLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Smart per-domain crawl-rate controller (AIMD). Runs every minute and, for each domain
 * currently being crawled, lets {@see DomainRateLimiter::adjust()} decide:
 *   - healthy (latency near baseline, no block) → additive increase (+rate_step), up to rate_max;
 *   - site SLOWING (recent latency >> baseline)  → multiplicative decrease (halve), BEFORE a block;
 *   - just blocked                               → held at the floor for the cooldown.
 * So each domain self-tunes: probe faster while the site keeps up, ease off the moment it
 * struggles. Per domain, independent. The rate itself is read by the workers' DomainRateLimiter.
 */
class RampCrawlRates extends Command
{
    protected $signature = 'ebq:ramp-crawl-rates';

    protected $description = 'Smart per-domain crawl rate (AIMD): ramp up while healthy, back off when a site slows or blocks.';

    public function handle(DomainRateLimiter $limiter): int
    {
        $domains = CrawlSite::where('status', 'crawling')
            ->pluck('normalized_domain')->filter()->unique();

        foreach ($domains as $domain) {
            $r = $limiter->adjust($domain);
            $this->line("{$domain}: {$r['action']} → rate={$r['rate']}/s"
                .($r['lat'] !== null ? " (lat={$r['lat']}ms base={$r['base']}ms)" : ''));
            if (in_array($r['action'], ['up', 'down(slow)'], true)) {
                Log::info('RampCrawlRates: '.$r['action'], ['domain' => $domain] + $r);
            }
        }

        return self::SUCCESS;
    }
}
