<?php

namespace App\Services\Crawler;

use App\Models\CrawlSite;
use App\Support\AutoscalerConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fleet-wide per-domain politeness. The crawler's local `delay_ms` only paces
 * fetches WITHIN one batch on one worker — with many autoscaled worker boxes,
 * several could hit the same domain at once and get the crawler blocked. This
 * Redis-backed (the RateLimiter facade uses the cache store = shared Redis)
 * limiter caps the WHOLE fleet to `per_domain_rate` requests/second/domain.
 *
 * Over-rate ⇒ short bounded wait, then proceed (fail-open) — never pin a worker
 * forever. Because crawl batches interleave many domains (per-pass fairness), a
 * worker briefly waiting on one hot domain is cheap relative to fetch latency.
 *
 * See infra/crawler/autoscaling.md.
 */
class DomainRateLimiter
{
    /** Block until a token for this domain is free (or the max wait elapses). */
    public function throttle(?string $domain): void
    {
        $domain = $this->normalize($domain);
        if ($domain === '') {
            return;
        }
        $rate = max(1, AutoscalerConfig::perDomainRate());
        $key = 'crawl-rate:'.$domain;
        $maxWaitMs = max(0, (int) config('crawler.rate_max_wait_ms', 5000));
        $waited = 0;

        while (true) {
            if (! RateLimiter::tooManyAttempts($key, $rate)) {
                RateLimiter::hit($key, 1); // 1-second decay window → $rate per second
                return;
            }
            if ($waited >= $maxWaitMs) {
                // Fail-open: a rare over-rate fetch beats a stuck crawl. Logged so we
                // can tune per_domain_rate / max_wait if a domain is consistently hot.
                Log::info('DomainRateLimiter: max wait reached, proceeding', ['domain' => $domain, 'rate' => $rate]);

                return;
            }
            usleep(100_000); // 100ms
            $waited += 100;
        }
    }

    /** Collapse a host or URL to the CrawlSite normalized form so all hosts of a domain share one bucket. */
    private function normalize(?string $domain): string
    {
        $d = trim((string) $domain);

        return $d === '' ? '' : CrawlSite::normalizeDomain($d);
    }
}
