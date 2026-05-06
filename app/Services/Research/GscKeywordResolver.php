<?php

namespace App\Services\Research;

use App\Models\Research\Keyword;
use App\Models\SearchConsoleData;

/**
 * Pipeline 3 — resolves GSC (query, country) tuples into Keyword rows.
 * Idempotent. Used by the daily MapGscQueriesToKeywordsJob to attach a
 * keyword_id to new GSC rows after sync, and by ResearchBackfill to fix
 * historical rows.
 */
class GscKeywordResolver
{
    /**
     * Walk distinct unmapped GSC tuples and link them. Returns the number
     * of rows updated.
     */
    public function resolveForWebsite(int $websiteId, ?int $limit = null): int
    {
        $query = SearchConsoleData::query()
            ->select('query', 'country')
            ->where('website_id', $websiteId)
            ->where('query', '!=', '')
            ->whereNull('keyword_id')
            ->groupBy('query', 'country');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $tuples = $query->get();
        $updated = 0;

        foreach ($tuples as $tuple) {
            $keyword = $this->resolveOrCreate((string) $tuple->query, (string) $tuple->country);
            $updated += SearchConsoleData::query()
                ->where('website_id', $websiteId)
                ->where('query', $tuple->query)
                ->where('country', $tuple->country)
                ->whereNull('keyword_id')
                ->update(['keyword_id' => $keyword->id]);
        }

        return $updated;
    }

    public function resolveOrCreate(string $query, string $country): Keyword
    {
        $normalisedCountry = mb_strtolower(trim($country));
        if ($normalisedCountry === '') {
            $normalisedCountry = 'global';
        }

        return Keyword::firstOrCreate(
            [
                'query_hash' => Keyword::hashFor($query),
                'country' => $normalisedCountry,
                'language' => 'en',
            ],
            [
                'query' => $query,
                'normalized_query' => Keyword::normalize($query),
            ]
        );
    }
}
