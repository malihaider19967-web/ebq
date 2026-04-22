<?php

namespace App\Services;

use App\Jobs\FetchCompetitorBacklinks;
use App\Models\CompetitorBacklink;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Sole entrypoint for reading / writing competitor-backlink data. Enforces:
 *  - Never re-bill on fresh cache (30-day TTL by default).
 *  - Hard cap at `limit_per_competitor` backlinks per domain.
 *  - Cache is keyed on the normalized domain, so two audits mentioning the
 *    same competitor share one fetch.
 */
class CompetitorBacklinkService
{
    public function __construct(private DataForSeoBacklinkClient $client)
    {
    }

    /**
     * Up to 50 cached backlinks for a single domain (pure DB read).
     *
     * @return Collection<int, CompetitorBacklink>
     */
    public function backlinksFor(string $domain): Collection
    {
        $domain = CompetitorBacklink::extractDomain($domain);
        if ($domain === '') {
            return CompetitorBacklink::query()->whereRaw('1=0')->get();
        }

        return CompetitorBacklink::query()
            ->forDomain($domain)
            ->orderByDesc('domain_authority')
            ->limit($this->limit())
            ->get();
    }

    /**
     * Has this domain been fetched and is the cache still fresh?
     */
    public function isFresh(string $domain): bool
    {
        $domain = CompetitorBacklink::extractDomain($domain);
        if ($domain === '') {
            return false;
        }

        return CompetitorBacklink::query()
            ->forDomain($domain)
            ->fresh()
            ->exists();
    }

    /**
     * Queue a background refresh for any domain that isn't already fresh.
     * Safe to call on every audit — it no-ops for cached entries.
     *
     * @param  list<string>  $domains
     */
    public function queueRefresh(array $domains): void
    {
        $toFetch = [];
        foreach ($domains as $d) {
            $normalized = CompetitorBacklink::extractDomain((string) $d);
            if ($normalized === '' || $this->isFresh($normalized)) {
                continue;
            }
            $toFetch[$normalized] = true;
        }

        $toFetch = array_keys($toFetch);
        if ($toFetch !== []) {
            FetchCompetitorBacklinks::dispatch($toFetch);
        }
    }

    /**
     * Synchronous fetch + upsert for a single domain. Returns the number of
     * rows written. Called by the job and by any CLI-driven refresh.
     */
    public function refresh(string $domain): int
    {
        $domain = CompetitorBacklink::extractDomain($domain);
        if ($domain === '') {
            return 0;
        }

        Log::info('CompetitorBacklinkService.refresh: starting', ['domain' => $domain]);

        $items = $this->client->backlinksForDomain($domain, $this->limit());
        if ($items === null) {
            Log::warning('CompetitorBacklinkService.refresh: client returned null', ['domain' => $domain]);

            return 0;
        }

        $freshDays = max(1, (int) config('services.competitor_backlinks.fresh_days', 30));
        $fetchedAt = Carbon::now();
        $expiresAt = $fetchedAt->copy()->addDays($freshDays);
        $limit = $this->limit();

        // Wipe any prior rows for this domain that fall outside the new top-N —
        // otherwise stale rows from an older fetch would linger forever.
        $keepHashes = [];
        $written = 0;

        foreach (array_slice($items, 0, $limit) as $item) {
            $url = isset($item['url_from']) && is_string($item['url_from']) ? trim($item['url_from']) : '';
            if ($url === '') {
                continue;
            }

            $hash = CompetitorBacklink::hashUrl($url);
            $keepHashes[] = $hash;

            $type = null;
            if (array_key_exists('dofollow', $item)) {
                $type = $item['dofollow'] === true ? 'dofollow' : 'nofollow';
            } elseif (isset($item['backlink_type']) && is_string($item['backlink_type'])) {
                $type = strtolower($item['backlink_type']);
            }

            $firstSeen = null;
            if (isset($item['first_seen']) && is_string($item['first_seen'])) {
                try {
                    $firstSeen = Carbon::parse($item['first_seen'])->toDateString();
                } catch (\Throwable) {
                    $firstSeen = null;
                }
            }

            CompetitorBacklink::updateOrCreate(
                [
                    'competitor_domain' => $domain,
                    'referring_page_hash' => $hash,
                ],
                [
                    'referring_page_url' => $url,
                    'referring_domain' => isset($item['domain_from']) && is_string($item['domain_from']) ? strtolower(trim($item['domain_from'])) : null,
                    'anchor_text' => isset($item['anchor']) && is_string($item['anchor']) ? mb_substr(trim($item['anchor']), 0, 500) : null,
                    'domain_authority' => isset($item['domain_from_rank']) && is_numeric($item['domain_from_rank'])
                        ? min(100, max(0, (int) $item['domain_from_rank']))
                        : null,
                    'backlink_type' => $type,
                    'first_seen_at' => $firstSeen,
                    'fetched_at' => $fetchedAt,
                    'expires_at' => $expiresAt,
                ]
            );
            $written++;
        }

        // Prune rows that weren't in this top-N (previous fetch had more/different links).
        if ($keepHashes !== []) {
            CompetitorBacklink::query()
                ->forDomain($domain)
                ->whereNotIn('referring_page_hash', $keepHashes)
                ->delete();
        }

        Log::info('CompetitorBacklinkService.refresh: done', [
            'domain' => $domain,
            'written' => $written,
            'capped_at' => $limit,
        ]);

        return $written;
    }

    private function limit(): int
    {
        return max(1, min(1000, (int) config('services.competitor_backlinks.limit_per_competitor', 50)));
    }
}
