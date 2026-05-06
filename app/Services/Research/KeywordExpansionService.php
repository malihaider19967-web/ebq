<?php

namespace App\Services\Research;

use App\Models\Research\Keyword;
use App\Models\Research\SerpFeature;
use App\Models\Research\SerpSnapshot;
use App\Models\Website;
use App\Services\Research\Quota\ResearchCostLogger;
use App\Services\Research\Quota\ResearchQuotaService;
use App\Services\SerperSearchClient;
use Illuminate\Support\Collection;

/**
 * Pipeline 1 — turn a seed keyword into a list of related keywords by
 * combining a SERP fetch (PAA + relatedSearches) with the Serper
 * autocomplete endpoint. Persisted Keyword rows are returned, so the
 * caller can fan out enrichment / SERP ingestion for the new entries.
 */
class KeywordExpansionService
{
    public function __construct(
        private readonly SerperSearchClient $serper,
        private readonly SerpIngestionService $serpIngest,
        private readonly ResearchQuotaService $quota,
        private readonly ResearchCostLogger $cost,
    ) {}

    /**
     * @return Collection<int, Keyword>  All keywords (seed + newly-expanded).
     */
    public function expand(string $seed, string $country = 'us', ?Website $website = null): Collection
    {
        $seed = trim($seed);
        if ($seed === '') {
            return collect();
        }

        $seedKeyword = $this->upsertKeyword($seed, $country);
        $found = collect([$seedKeyword]);

        $snapshot = $this->serpIngest->ingest($seedKeyword, website: $website);
        if ($snapshot !== null) {
            foreach ($this->harvestSerpFeatures($snapshot) as $candidate) {
                $found->push($this->upsertKeyword($candidate, $country));
            }
        }

        $this->quota->assertCanSpend($website, 'serp_fetch', 1);
        $autocomplete = $this->serper->query([
            'q' => $seed,
            'type' => 'autocomplete',
            'gl' => $country !== 'global' ? $country : null,
            '__website_id' => $website?->id,
        ]);

        if (is_array($autocomplete)) {
            $this->cost->log('serp_fetch', $website?->id, 'serper', [
                'operation' => 'autocomplete',
                'seed' => mb_substr($seed, 0, 120),
            ], 1);

            foreach ($this->normaliseAutocomplete($autocomplete) as $candidate) {
                $found->push($this->upsertKeyword($candidate, $country));
            }
        }

        return $found->unique('id')->values();
    }

    private function upsertKeyword(string $query, string $country): Keyword
    {
        return Keyword::firstOrCreate(
            [
                'query_hash' => Keyword::hashFor($query),
                'country' => $country,
                'language' => 'en',
            ],
            [
                'query' => $query,
                'normalized_query' => Keyword::normalize($query),
            ]
        );
    }

    /** @return list<string> */
    private function harvestSerpFeatures(SerpSnapshot $snapshot): array
    {
        $out = [];
        $features = SerpFeature::query()
            ->where('snapshot_id', $snapshot->id)
            ->whereIn('feature_type', ['paa', 'related'])
            ->get();

        foreach ($features as $feature) {
            $items = is_array($feature->payload) ? $feature->payload : [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $candidate = (string) ($item['question'] ?? $item['query'] ?? '');
                if ($candidate !== '') {
                    $out[] = $candidate;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function normaliseAutocomplete(array $payload): array
    {
        $list = $payload['suggestions'] ?? $payload['data'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $item) {
            if (is_string($item)) {
                $out[] = $item;
                continue;
            }
            if (is_array($item)) {
                $value = (string) ($item['value'] ?? $item['query'] ?? $item['suggestion'] ?? '');
                if ($value !== '') {
                    $out[] = $value;
                }
            }
        }

        return array_values(array_unique($out));
    }
}
