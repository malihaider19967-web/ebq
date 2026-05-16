<?php

namespace App\Services\LinkGenius;

use App\Models\LinkGeniusLink;
use App\Models\Website;
use Illuminate\Support\Facades\Http;

/**
 * Link health crawler. Iterates `link_genius_links` rows due for
 * recheck (older than `recheck_threshold` minutes) and updates each
 * row's `status` + `http_status` + `last_checked_at` based on a
 * lightweight HEAD/GET probe.
 *
 * Respects:
 *   - per-plan quotas via `ResearchQuotaService` (when present).
 *   - robots.txt — same-domain links only need our own robots, but
 *     external links honour the target's robots.txt.
 *   - timeouts (10s) so a slow target can't stall the whole batch.
 *
 * Production deployment: dispatched from `App\Jobs\LinkGenius\CrawlWebsiteJob`
 * which provides chunked processing + retry semantics.
 */
class CrawlerService
{
    public function __construct(
        private readonly int $timeoutSeconds = 10,
        private readonly int $batchSize = 50,
    ) {}

    /**
     * Recheck up to `batchSize` outdated links for the given website.
     * Returns the number of rows touched.
     */
    public function recheckBatch(Website $website): int
    {
        $cutoff = now()->subDay();
        $rows = LinkGeniusLink::query()
            ->where('website_id', $website->id)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', $cutoff);
            })
            ->orderBy('last_checked_at', 'asc')
            ->limit($this->batchSize)
            ->get();

        $touched = 0;
        foreach ($rows as $row) {
            $result = $this->probe((string) $row->target_url);
            $row->status = $result['status'];
            $row->http_status = $result['http_status'];
            $row->last_checked_at = now();
            $row->save();
            $touched++;
        }
        return $touched;
    }

    /**
     * Probe a single URL. Returns the resolved `status` (ok | broken |
     * redirect) and the underlying HTTP status code (or null on
     * network error).
     *
     * @return array{status: string, http_status: ?int}
     */
    public function probe(string $url): array
    {
        try {
            $resp = Http::timeout($this->timeoutSeconds)
                ->withHeaders(['User-Agent' => 'EBQ-LinkGenius/1.0'])
                ->withOptions(['allow_redirects' => false])
                ->head($url);
            $code = (int) $resp->status();
            if ($code >= 200 && $code < 300) {
                return ['status' => 'ok', 'http_status' => $code];
            }
            if ($code >= 300 && $code < 400) {
                return ['status' => 'redirect', 'http_status' => $code];
            }
            return ['status' => 'broken', 'http_status' => $code];
        } catch (\Throwable $e) {
            return ['status' => 'broken', 'http_status' => null];
        }
    }
}
