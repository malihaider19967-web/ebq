<?php

namespace App\Services;

use App\Enums\BacklinkType;
use App\Models\Backlink;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the website's OWN backlinks from Keywords Everywhere and upserts
 * them into the `backlinks` table. Distinct from `CompetitorBacklinkService`,
 * which targets competitor domains and writes to `competitor_backlinks`.
 *
 * 30-day cache gate
 * ─────────────────
 * KE charges per result row, so we only refresh once per website per month
 * regardless of how often the live-score endpoint asks. The cache marker is
 * set even on empty/failed responses so we don't retry on every page load.
 *
 * Re-trigger
 * ──────────
 * To force a fresh fetch (e.g., after a link-building campaign) call
 * `forget($websiteId)` from a Tinker shell or a future "refresh now" UI.
 */
class OwnBacklinkSyncService
{
    public const CACHE_TTL_DAYS = 30;

    public function __construct(private readonly KeywordsEverywhereBacklinkClient $client) {}

    public function isFresh(int $websiteId): bool
    {
        return Cache::has($this->cacheKey($websiteId));
    }

    public function forget(int $websiteId): void
    {
        Cache::forget($this->cacheKey($websiteId));
    }

    /**
     * Runs the KE → Backlink upsert. No-op when the 30-day window is still
     * fresh. Returns the number of rows written/updated.
     */
    public function syncForWebsite(Website $website, ?int $ownerUserId = null): int
    {
        if ($this->isFresh($website->id)) {
            return 0;
        }

        $domain = $this->extractDomain((string) $website->domain);
        if ($domain === '') {
            // Mark fresh anyway — bad domain isn't going to fix itself in 30s.
            $this->markFresh($website->id);

            return 0;
        }

        $limit = (int) config('services.keywords_everywhere.own_backlinks_limit', 200);
        $limit = max(50, min(1000, $limit));

        $items = $this->client->backlinksForDomain(
            $domain,
            $limit,
            websiteId: $website->id,
            ownerUserId: $ownerUserId,
        );

        // ALWAYS mark fresh — even on null/empty — so we don't retry for
        // 30 days. The whole point is "once per month" gating.
        $this->markFresh($website->id);

        if (! is_array($items) || $items === []) {
            Log::info('OwnBacklinkSyncService: KE returned no backlinks', [
                'website_id' => $website->id,
                'domain' => $domain,
            ]);

            return 0;
        }

        $today = Carbon::today();
        $written = 0;

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $referring = trim((string) ($row['url_source'] ?? ''));
            $target = trim((string) ($row['url_target'] ?? ''));
            if ($referring === '' || $target === '') {
                continue;
            }
            $anchor = isset($row['anchor_text']) && is_string($row['anchor_text'])
                ? mb_substr(trim($row['anchor_text']), 0, 250)
                : null;

            // Use updateOrCreate by (website, referring, target) so re-syncs
            // refresh `tracked_date` instead of duplicating rows.
            Backlink::query()->updateOrCreate(
                [
                    'website_id' => $website->id,
                    'referring_page_url' => $referring,
                    'target_page_url' => $target,
                ],
                [
                    'tracked_date' => $today,
                    'anchor_text' => $anchor,
                    'type' => BacklinkType::Other->value,
                    'is_dofollow' => true,
                ],
            );
            $written++;
        }

        Log::info('OwnBacklinkSyncService: synced from KE', [
            'website_id' => $website->id,
            'domain' => $domain,
            'returned' => count($items),
            'written' => $written,
        ]);

        return $written;
    }

    private function markFresh(int $websiteId): void
    {
        Cache::put(
            $this->cacheKey($websiteId),
            Carbon::now()->toIso8601String(),
            Carbon::now()->addDays(self::CACHE_TTL_DAYS),
        );
    }

    private function cacheKey(int $websiteId): string
    {
        return 'ke_own_backlinks_synced:' . $websiteId;
    }

    /**
     * Bare host, lowercased, www-stripped. KE wants a domain, not a URL.
     */
    private function extractDomain(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $forParse = str_contains($raw, '://') ? $raw : 'https://' . $raw;
        $host = parse_url($forParse, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return strtolower($raw);
        }

        return preg_replace('/^www\./', '', strtolower($host)) ?: $host;
    }
}
