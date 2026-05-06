<?php

namespace App\Services\Research\Intelligence;

use App\Models\Research\SerpResult;

/**
 * Pure-PHP difficulty scoring over a SERP result set. No I/O.
 *
 * Two outputs:
 *   - serp_strength (0..100): how strong the SERP itself looks (high = entrenched).
 *   - difficulty    (0..100): blend of strength + quality density.
 *
 * Inputs only the top-10 organic results. Caller is responsible for
 * passing pre-fetched, ordered rows.
 */
class KeywordDifficultyEngine
{
    /** Domains that boost SERP strength materially. */
    private const HIGH_AUTHORITY = [
        'wikipedia.org', 'amazon.com', 'youtube.com', 'reddit.com', 'forbes.com',
        'nytimes.com', 'theguardian.com', 'bbc.co.uk', 'medium.com', 'github.com',
    ];

    /**
     * @param  list<SerpResult>  $results
     * @return array{difficulty:int, serp_strength:int}
     */
    public function score(array $results): array
    {
        if ($results === []) {
            return ['difficulty' => 0, 'serp_strength' => 0];
        }

        $domains = [];
        $highAuth = 0;
        $lowQual = 0;
        $organicCount = 0;

        foreach ($results as $result) {
            if ($result->result_type !== 'organic') {
                continue;
            }
            $organicCount++;
            $domain = mb_strtolower((string) $result->domain);
            $domains[$domain] = ($domains[$domain] ?? 0) + 1;

            foreach (self::HIGH_AUTHORITY as $auth) {
                if (str_ends_with($domain, $auth)) {
                    $highAuth++;
                    break;
                }
            }

            if ($result->is_low_quality) {
                $lowQual++;
            }
        }

        if ($organicCount === 0) {
            return ['difficulty' => 0, 'serp_strength' => 0];
        }

        $uniqueDomains = count($domains);
        $diversityComponent = (1 - ($uniqueDomains / max(1, $organicCount))) * 100; // less unique = stronger
        $authorityComponent = min(100, ($highAuth / $organicCount) * 200);
        $serpStrength = (int) round(0.6 * $authorityComponent + 0.4 * $diversityComponent);
        $serpStrength = max(0, min(100, $serpStrength));

        // Difficulty discounts strength when low-quality results dominate.
        $difficulty = $serpStrength - (int) round(($lowQual / $organicCount) * 25);
        $difficulty = max(0, min(100, $difficulty));

        return ['difficulty' => $difficulty, 'serp_strength' => $serpStrength];
    }
}
