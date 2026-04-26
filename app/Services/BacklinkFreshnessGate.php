<?php

namespace App\Services;

use App\Models\Backlink;
use App\Models\CompetitorBacklink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for "should we call Keywords Everywhere for this
 * domain right now?" — applied uniformly to every code path that fetches
 * backlink data (WP plugin live-score sync, EBQ page audit, custom audit,
 * CLI scripts, future call sites).
 *
 * Rule
 * ────
 * If we already stored backlink rows for this domain in the last
 * `services.keywords_everywhere.backlinks_ttl_days` (default 30, override
 * with `KE_BACKLINKS_TTL_DAYS` in .env), the gate returns `isFresh = true`
 * and the caller MUST serve stored results without billing KE.
 *
 * Storage check considers both:
 *   - `backlinks` table (our own backlinks for any tracked website),
 *      matched on the URL host.
 *   - `competitor_backlinks` table, matched on `competitor_domain`.
 *
 * Empty-result handling
 * ─────────────────────
 * KE can legitimately return zero backlinks for a small / new domain. Without
 * a marker we'd retry every page load forever. So `markFetched()` writes a
 * cache sentinel with the same TTL — which `isFresh()` also honors — so the
 * "we tried, KE returned nothing" case is treated as fresh.
 *
 * Domain normalization
 * ────────────────────
 * Lowercased + www-stripped + scheme-stripped. `https://www.Example.com/path`,
 * `Example.com`, and `example.com` all collapse to `example.com` so the gate
 * can't be bypassed by a casing or trailing-slash variant.
 */
class BacklinkFreshnessGate
{
    /**
     * Cache sentinel key — set by `markFetched()` so even a 0-result KE call
     * counts as "fresh" for the TTL window. Without it we'd hammer KE every
     * page-load on small domains that legitimately have no backlinks yet.
     */
    private const CACHE_PREFIX = 'ke_backlinks_fetched:';

    /**
     * TTL window read from config, fallback 30 days. Configured via the
     * `KE_BACKLINKS_TTL_DAYS` env var. Min-clamped to 1 day to prevent
     * accidental "always fresh" via 0 / negative values.
     */
    public function ttlDays(): int
    {
        $raw = (int) config('services.keywords_everywhere.backlinks_ttl_days', 30);

        return max(1, $raw);
    }

    /**
     * True when stored data for the domain is younger than the TTL window —
     * either real backlink rows OR a fetch sentinel from a 0-result call.
     * Callers MUST early-return without hitting KE when this is true.
     */
    public function isFresh(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') {
            return false;
        }

        // Sentinel covers the "we called KE, it returned 0 results" case
        // where no DB rows would exist as evidence. Cheap O(1) lookup, so
        // we check it first.
        if (Cache::has($this->cacheKey($domain))) {
            return true;
        }

        $cutoff = Carbon::now()->subDays($this->ttlDays());

        // CompetitorBacklink: column is exact-match `competitor_domain`,
        // and `fetched_at` is the canonical "when did we get this from KE"
        // stamp set by CompetitorBacklinkService::refresh().
        $hasCompetitor = CompetitorBacklink::query()
            ->where('competitor_domain', $domain)
            ->where('fetched_at', '>=', $cutoff)
            ->exists();
        if ($hasCompetitor) {
            return true;
        }

        // Backlink: full URLs in `referring_page_url` / `target_page_url`,
        // so we LIKE-search the host. created_at is the row insertion time
        // — by `OwnBacklinkSyncService` (KE-driven) or by manual entry.
        // Either source counts as evidence that we've seen this domain.
        $patterns = $this->urlLikePatterns($domain);
        $hasOwn = Backlink::query()
            ->where('created_at', '>=', $cutoff)
            ->where(function ($q) use ($patterns) {
                foreach ($patterns as $p) {
                    $q->orWhere('referring_page_url', 'LIKE', $p)
                      ->orWhere('target_page_url', 'LIKE', $p);
                }
            })
            ->exists();

        return $hasOwn;
    }

    /**
     * Mark a domain as fetched right now. Call this after every successful
     * KE round-trip — including 0-result ones — so the TTL clock starts and
     * we don't re-bill on the next page load.
     */
    public function markFetched(string $domain): void
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') {
            return;
        }
        Cache::put(
            $this->cacheKey($domain),
            Carbon::now()->toIso8601String(),
            Carbon::now()->addDays($this->ttlDays()),
        );
    }

    /**
     * Force a re-fetch. Used by manual "refresh now" UIs and tests. Only
     * forgets the sentinel — actual stored rows are kept (they remain valid
     * historical data; they're just no longer enough on their own to satisfy
     * the gate).
     */
    public function forget(string $domain): void
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') {
            return;
        }
        Cache::forget($this->cacheKey($domain));
    }

    /**
     * Forget ALL known cached freshness sentinels.
     *
     * Without a Redis pattern-scan or tagged cache (which we deliberately
     * don't depend on, so the gate works on every cache driver), the only
     * portable way to enumerate sentinels is to derive the candidate
     * domain list from the data tables that would ever have produced one:
     *   - `competitor_backlinks.competitor_domain` (every competitor we've
     *     ever fetched from KE)
     *   - hosts of `backlinks.referring_page_url` + `target_page_url`
     *
     * Pass `$websiteId` to scope to one site; omit for a full cross-site
     * sweep (use sparingly — flushes every connected site's KE freshness).
     *
     * Returns the count of domains whose sentinel was attempted-forgotten
     * (Cache::forget always returns true; we report attempts not actual
     * "had a key" because the gate is best-effort by design).
     */
    public function forgetAll(?int $websiteId = null): int
    {
        $domains = [];

        // 1. Competitor side — explicit `competitor_domain` column.
        CompetitorBacklink::query()
            ->select('competitor_domain')->distinct()
            ->get()
            ->each(function ($row) use (&$domains) {
                $d = $this->normalizeDomain((string) $row->competitor_domain);
                if ($d !== '') $domains[$d] = true;
            });

        // 2. Own backlinks side — extract host from each URL. Scope by
        //    website when a specific website was named.
        $q = Backlink::query()->select('referring_page_url', 'target_page_url');
        if ($websiteId !== null) $q->where('website_id', $websiteId);
        $q->get()->each(function ($row) use (&$domains) {
            foreach (['referring_page_url', 'target_page_url'] as $col) {
                $host = parse_url((string) $row->$col, PHP_URL_HOST);
                if (is_string($host) && $host !== '') {
                    $domains[$this->normalizeDomain($host)] = true;
                }
            }
        });

        unset($domains['']);
        foreach (array_keys($domains) as $d) {
            Cache::forget($this->cacheKey($d));
        }
        return count($domains);
    }

    /**
     * Bare host, lowercased, www-stripped. Mirrors the input normalization
     * KE expects — no scheme, no path, no port.
     */
    private function normalizeDomain(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $forParse = str_contains($raw, '://') ? $raw : 'https://' . $raw;
        $host = parse_url($forParse, PHP_URL_HOST);
        $host = is_string($host) && $host !== '' ? strtolower($host) : strtolower($raw);

        return preg_replace('/^www\./', '', $host) ?: $host;
    }

    /**
     * @return list<string>
     */
    private function urlLikePatterns(string $domain): array
    {
        $domain = addcslashes($domain, '\\%_');

        return [
            '%://' . $domain . '/%',
            '%://' . $domain,
            '%://www.' . $domain . '/%',
            '%://www.' . $domain,
        ];
    }

    private function cacheKey(string $domain): string
    {
        return self::CACHE_PREFIX . $domain;
    }
}
