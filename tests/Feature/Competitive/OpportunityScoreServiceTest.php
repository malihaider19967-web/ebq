<?php

namespace Tests\Feature\Competitive;

use App\Services\Competitive\OpportunityScoreService;
use Tests\TestCase;

class OpportunityScoreServiceTest extends TestCase
{
    private function service(): OpportunityScoreService
    {
        return app(OpportunityScoreService::class);
    }

    public function test_crowded_high_authority_serp_scores_low(): void
    {
        $result = $this->service()->score(
            avgTop10Da: 90.0,
            serpFeatures: ['answerBox' => true, 'knowledgeGraph' => true, 'ads' => true, 'shopping' => true, 'paa' => 4],
            volume: 500,
            ourPosition: null,
        );

        $this->assertLessThan(40, $result['score']);
    }

    public function test_clean_low_authority_high_volume_striking_distance_scores_high(): void
    {
        $result = $this->service()->score(
            avgTop10Da: 15.0,
            serpFeatures: [],
            volume: 50000,
            ourPosition: 8.0,
        );

        $this->assertGreaterThan(70, $result['score']);
    }

    public function test_null_authority_is_treated_as_neutral(): void
    {
        $neutral = $this->service()->score(null, [], 1000, null);
        $half = $this->service()->score(50.0, [], 1000, null);

        // DA 50/100 == the null neutral baseline, so scores match.
        $this->assertSame($half['score'], $neutral['score']);
    }

    public function test_score_is_clamped_to_0_100(): void
    {
        $max = $this->service()->score(0.0, [], 1_000_000, 10.0);
        $min = $this->service()->score(100.0, ['answerBox' => true, 'knowledgeGraph' => true, 'ads' => true, 'shopping' => true, 'paa' => 10], 1, 50.0);

        $this->assertGreaterThanOrEqual(0, $min['score']);
        $this->assertLessThanOrEqual(100, $max['score']);
    }

    public function test_components_are_exposed_for_the_tooltip(): void
    {
        $result = $this->service()->score(62.0, ['answerBox' => true, 'paa' => 5], 12000, 9.0);

        $this->assertSame(62.0, $result['components']['avg_top10_da']);
        $this->assertContains('answer box', $result['components']['serp_features']);
        $this->assertContains('people also ask', $result['components']['serp_features']);
        $this->assertSame(12000, $result['components']['volume']);
    }
}
