<?php

namespace App\Services\Research;

use App\Models\Research\ResearchTarget;
use App\Models\Website;
use App\Services\SerperSearchClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns keywords (or a Website's top GSC queries) into a list of
 * competitor domains and upserts them into `research_targets` so the
 * scheduled `ebq:research-scan-next` worker eventually scrapes them.
 *
 * Discovery sources:
 *   - explicit keyword list passed in (manual seeding from admin)
 *   - top GSC queries for an attached Website (auto-onboarding)
 *
 * Each SERP call costs Serper credits, so the service caps how many
 * keywords it queries per invocation.
 */
class CompetitorDiscoveryService
{
    public function __construct(private readonly SerperSearchClient $serper) {}

    /**
     * @param  list<string>  $keywords
     * @param  list<string>  $excludeDomains  registered domains we should never auto-enqueue
     * @return Collection<int, ResearchTarget>  upserted target rows (existing + new)
     */
    public function discoverFromKeywords(
        array $keywords,
        ?Website $attachedWebsite = null,
        array $excludeDomains = [],
        int $maxKeywords = 5,
        int $topResultsPerKeyword = 10,
        string $country = 'us',
    ): Collection {
        $kws = array_values(array_filter(array_map(fn ($k) => trim((string) $k), $keywords)));
        if ($kws === []) {
            return collect();
        }

        $excludeSet = collect($excludeDomains)
            ->map(fn ($d) => mb_strtolower(trim((string) $d)))
            ->filter()
            ->flip();

        $domainsSeen = [];

        foreach (array_slice($kws, 0, $maxKeywords) as $keyword) {
            $payload = $this->serper->query([
                'q' => $keyword,
                'type' => 'organic',
                'num' => $topResultsPerKeyword,
                'gl' => $country,
                '__website_id' => $attachedWebsite?->id,
                '__owner_user_id' => $attachedWebsite?->user_id,
                '__source' => 'research',
            ]);

            if (! is_array($payload)) {
                continue;
            }

            foreach ((array) ($payload['organic'] ?? []) as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $url = (string) ($row['link'] ?? $row['url'] ?? '');
                if ($url === '') {
                    continue;
                }
                $domain = $this->registeredDomain($url);
                if ($domain === '' || isset($excludeSet[$domain])) {
                    continue;
                }
                $rank = (int) ($row['position'] ?? ($i + 1));
                $domainsSeen[$domain] = [
                    'best_rank' => min($domainsSeen[$domain]['best_rank'] ?? 999, $rank),
                    'sample_url' => $domainsSeen[$domain]['sample_url'] ?? $url,
                    'matched_keyword' => $domainsSeen[$domain]['matched_keyword'] ?? $keyword,
                ];
            }
        }

        if ($domainsSeen === []) {
            return collect();
        }

        $upserted = collect();
        foreach ($domainsSeen as $domain => $meta) {
            // Better rank → higher priority. Top-3 = 90, top-10 = 70, beyond = 50.
            $priority = match (true) {
                $meta['best_rank'] <= 3 => 90,
                $meta['best_rank'] <= 10 => ResearchTarget::PRIORITY_DIRECT_COMPETITOR,
                default => ResearchTarget::PRIORITY_SERP_DOMAIN,
            };

            $target = ResearchTarget::query()->where('domain', $domain)->first();
            if ($target === null) {
                $target = ResearchTarget::create([
                    'domain' => $domain,
                    'root_url' => $this->rootUrl($meta['sample_url']),
                    'source' => ResearchTarget::SOURCE_SERP_COMPETITOR,
                    'priority' => $priority,
                    'status' => ResearchTarget::STATUS_QUEUED,
                    'attached_website_id' => $attachedWebsite?->id,
                    'seed_keywords' => array_slice($kws, 0, $maxKeywords),
                    'notes' => "Auto-discovered: best rank {$meta['best_rank']} for \"{$meta['matched_keyword']}\".",
                ]);
            } else {
                // Existing target: bump priority if this discovery is stronger;
                // never demote.
                $target->forceFill([
                    'priority' => max((int) $target->priority, $priority),
                    'attached_website_id' => $target->attached_website_id ?? $attachedWebsite?->id,
                ])->save();
            }
            $upserted->push($target);
        }

        return $upserted;
    }

    /**
     * @return Collection<int, ResearchTarget>
     */
    public function discoverForWebsite(Website $website, int $maxKeywords = 5): Collection
    {
        $excludeDomains = [$this->registeredDomain((string) $website->domain)];

        $topQueries = \App\Models\SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->where('query', '!=', '')
            ->whereDate('date', '>=', now()->subDays(90)->toDateString())
            ->selectRaw('query, SUM(impressions) AS imps')
            ->groupBy('query')
            ->orderByDesc('imps')
            ->limit($maxKeywords)
            ->pluck('query')
            ->all();

        if ($topQueries === []) {
            return collect();
        }

        return $this->discoverFromKeywords(
            keywords: $topQueries,
            attachedWebsite: $website,
            excludeDomains: $excludeDomains,
            maxKeywords: $maxKeywords,
        );
    }

    private function registeredDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $host = mb_strtolower(trim((string) $host));
        return preg_replace('/^www\./', '', $host) ?: $host;
    }

    private function rootUrl(string $sampleUrl): string
    {
        $parts = parse_url($sampleUrl);
        if (! is_array($parts) || empty($parts['host'])) {
            return $sampleUrl;
        }
        $scheme = $parts['scheme'] ?? 'https';
        return $scheme.'://'.$parts['host'].'/';
    }
}
