<?php

namespace App\Services\Research\Intelligence;

use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;

/**
 * Flags `serp_results.is_low_quality` for organic rows whose domain or
 * snippet pattern looks like UGC / forum / thin-content. Curated list of
 * "soft" domains based on the doc's example set; LLM classification on
 * snippets is left as a future extension.
 */
class SerpWeaknessEngine
{
    private const SOFT_DOMAINS = [
        'reddit.com', 'quora.com', 'medium.com', 'pinterest.com', 'pinterest.co.uk',
        'youtube.com', 'tiktok.com', 'facebook.com', 'twitter.com', 'x.com',
        'tripadvisor.com', 'yelp.com', 'wikihow.com',
    ];

    /** @return int Number of rows flagged. */
    public function scan(SerpSnapshot $snapshot): int
    {
        $rows = SerpResult::query()
            ->where('snapshot_id', $snapshot->id)
            ->where('result_type', 'organic')
            ->get();

        $flagged = 0;
        foreach ($rows as $row) {
            $isSoft = $this->isSoftDomain((string) $row->domain);
            if ($isSoft && ! $row->is_low_quality) {
                $row->is_low_quality = true;
                $row->save();
                $flagged++;
            }
        }

        return $flagged;
    }

    public function isSoftDomain(string $domain): bool
    {
        $domain = mb_strtolower(ltrim($domain, '.'));
        foreach (self::SOFT_DOMAINS as $soft) {
            if ($domain === $soft || str_ends_with($domain, '.'.$soft)) {
                return true;
            }
        }

        return false;
    }
}
