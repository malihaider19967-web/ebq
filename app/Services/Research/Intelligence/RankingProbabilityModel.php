<?php

namespace App\Services\Research\Intelligence;

/**
 * Bayesian-flavoured ranking probability:
 *
 *   P(top10) = σ( α + β·niche_aggregate − γ·difficulty + δ·content_match )
 *
 * Phase-2 ships fixed coefficients calibrated against the existing
 * AuditScore distribution. Phase-3+ swaps in per-niche calibrated
 * coefficients fed by NicheAggregateRecomputeService.
 */
class RankingProbabilityModel
{
    private const ALPHA = -1.5;
    private const BETA = 2.0;
    private const GAMMA = 0.04;
    private const DELTA = 1.0;

    /**
     * @param  float  $nicheAggregate  0..1, e.g. niche_aggregates.ranking_probability_score
     * @param  int    $difficulty      0..100
     * @param  float  $contentMatch    0..1, fraction of niche-typical headings/keywords present on the page
     */
    public function probability(float $nicheAggregate, int $difficulty, float $contentMatch): float
    {
        $z = self::ALPHA
            + self::BETA * max(0.0, min(1.0, $nicheAggregate))
            - self::GAMMA * max(0, min(100, $difficulty))
            + self::DELTA * max(0.0, min(1.0, $contentMatch));

        return round($this->sigmoid($z), 4);
    }

    private function sigmoid(float $z): float
    {
        return 1.0 / (1.0 + exp(-$z));
    }
}
