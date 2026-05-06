<?php

namespace App\Services\Research\Intelligence;

/**
 * Opportunity score for a (website, keyword) pair.
 *
 *   Opportunity = (impressions × CTR_gap × rank_potential + volume) / SERP_competition
 *
 *   CTR_gap        = max(0, niche_avg_ctr_at_target_position − current_ctr)
 *   rank_potential = (current_position − target_position) / 9   (0..1)
 *   competition    = max(1, difficulty)
 */
class OpportunityEngine
{
    /**
     * @param  array<int, float>  $nicheCtrByPosition  position(int 1..10) => avg ctr (0..1)
     * @return array{score:float, ctr_gap:float, rank_potential:float}
     */
    public function score(
        int $impressions30d,
        ?float $currentCtr,
        ?float $currentPosition,
        ?int $searchVolume,
        ?int $difficulty,
        array $nicheCtrByPosition,
        int $targetPosition = 3,
    ): array {
        $targetPosition = max(1, min(10, $targetPosition));

        $targetCtr = (float) ($nicheCtrByPosition[$targetPosition] ?? 0.0);
        $ctrGap = max(0.0, $targetCtr - (float) ($currentCtr ?? 0.0));

        $rankPotential = 0.0;
        if ($currentPosition !== null && $currentPosition > $targetPosition) {
            $rankPotential = max(0.0, min(1.0, ($currentPosition - $targetPosition) / 9.0));
        }

        $volume = (float) ($searchVolume ?? 0);
        $competition = max(1, (int) ($difficulty ?? 50));

        $score = ($impressions30d * $ctrGap * $rankPotential + $volume) / $competition;

        return [
            'score' => round($score, 2),
            'ctr_gap' => round($ctrGap, 4),
            'rank_potential' => round($rankPotential, 3),
        ];
    }
}
