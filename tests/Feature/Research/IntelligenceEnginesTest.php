<?php

namespace Tests\Feature\Research;

use App\Models\Research\Keyword;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use App\Services\Research\Intelligence\KeywordDifficultyEngine;
use App\Services\Research\Intelligence\OpportunityEngine;
use App\Services\Research\Intelligence\RankingProbabilityModel;
use App\Services\Research\Intelligence\SerpWeaknessEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IntelligenceEnginesTest extends TestCase
{
    use RefreshDatabase;

    private function makeSnapshot(string $query = 'best running shoes'): SerpSnapshot
    {
        $keyword = Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor($query), 'country' => 'us', 'language' => 'en'],
            ['query' => $query, 'normalized_query' => Keyword::normalize($query)]
        );

        return SerpSnapshot::create([
            'keyword_id' => $keyword->id,
            'device' => 'desktop',
            'country' => 'us',
            'fetched_at' => Carbon::now(),
            'fetched_on' => Carbon::today(),
        ]);
    }

    public function test_difficulty_engine_scores_high_authority_serps_higher(): void
    {
        $snapshot = $this->makeSnapshot();

        $strong = [];
        foreach (['nytimes.com', 'bbc.co.uk', 'forbes.com', 'wikipedia.org', 'amazon.com'] as $i => $domain) {
            $strong[] = SerpResult::create([
                'snapshot_id' => $snapshot->id,
                'rank' => $i + 1,
                'url' => "https://{$domain}/x",
                'domain' => $domain,
                'result_type' => 'organic',
            ]);
        }

        $weakSnap = $this->makeSnapshot('softer keyword');
        $weak = [];
        foreach (['somerandomblog.test', 'anotherblog.test', 'tinysite.test'] as $i => $domain) {
            $weak[] = SerpResult::create([
                'snapshot_id' => $weakSnap->id,
                'rank' => $i + 1,
                'url' => "https://{$domain}/x",
                'domain' => $domain,
                'result_type' => 'organic',
            ]);
        }

        $engine = new KeywordDifficultyEngine();
        $strongScore = $engine->score($strong);
        $weakScore = $engine->score($weak);

        $this->assertGreaterThan($weakScore['serp_strength'], $strongScore['serp_strength']);
        $this->assertGreaterThan(0, $strongScore['difficulty']);
    }

    public function test_difficulty_engine_returns_zero_for_empty_input(): void
    {
        $engine = new KeywordDifficultyEngine();
        $score = $engine->score([]);
        $this->assertSame(0, $score['difficulty']);
        $this->assertSame(0, $score['serp_strength']);
    }

    public function test_opportunity_engine_rewards_underperforming_high_traffic_keywords(): void
    {
        $engine = new OpportunityEngine();

        $opportunity = $engine->score(
            impressions30d: 5000,
            currentCtr: 0.01,
            currentPosition: 9.0,
            searchVolume: 1000,
            difficulty: 30,
            nicheCtrByPosition: [1 => 0.30, 2 => 0.18, 3 => 0.11, 9 => 0.02],
            targetPosition: 3,
        );

        $this->assertGreaterThan(0, $opportunity['score']);
        $this->assertGreaterThan(0, $opportunity['ctr_gap']);
        $this->assertGreaterThan(0, $opportunity['rank_potential']);
    }

    public function test_opportunity_engine_zero_when_already_at_target(): void
    {
        $engine = new OpportunityEngine();
        $r = $engine->score(
            impressions30d: 1000,
            currentCtr: 0.30,
            currentPosition: 1.0,
            searchVolume: 1000,
            difficulty: 50,
            nicheCtrByPosition: [1 => 0.30],
            targetPosition: 1,
        );
        $this->assertSame(0.0, $r['ctr_gap']);
        $this->assertSame(0.0, $r['rank_potential']);
    }

    public function test_ranking_probability_is_higher_for_low_difficulty_strong_match(): void
    {
        $model = new RankingProbabilityModel();

        $easy = $model->probability(nicheAggregate: 0.7, difficulty: 20, contentMatch: 0.8);
        $hard = $model->probability(nicheAggregate: 0.3, difficulty: 90, contentMatch: 0.2);

        $this->assertGreaterThan($hard, $easy);
        $this->assertGreaterThanOrEqual(0.0, $hard);
        $this->assertLessThanOrEqual(1.0, $easy);
    }

    public function test_serp_weakness_engine_flags_soft_domains(): void
    {
        $snapshot = $this->makeSnapshot('how to brew matcha');
        $rows = [
            ['domain' => 'reddit.com', 'expectFlagged' => true],
            ['domain' => 'r.reddit.com', 'expectFlagged' => true],
            ['domain' => 'pinterest.co.uk', 'expectFlagged' => true],
            ['domain' => 'mybrand.test', 'expectFlagged' => false],
            ['domain' => 'goodblog.test', 'expectFlagged' => false],
        ];
        foreach ($rows as $i => $row) {
            SerpResult::create([
                'snapshot_id' => $snapshot->id,
                'rank' => $i + 1,
                'url' => 'https://'.$row['domain'].'/x',
                'domain' => $row['domain'],
                'result_type' => 'organic',
            ]);
        }

        $flagged = (new SerpWeaknessEngine())->scan($snapshot);
        $this->assertSame(3, $flagged);

        foreach ($rows as $row) {
            $hit = SerpResult::query()
                ->where('snapshot_id', $snapshot->id)
                ->where('domain', $row['domain'])
                ->firstOrFail();
            $this->assertSame($row['expectFlagged'], (bool) $hit->is_low_quality, $row['domain']);
        }
    }
}
