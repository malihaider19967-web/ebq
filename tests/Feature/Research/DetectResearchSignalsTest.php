<?php

namespace Tests\Feature\Research;

use App\Jobs\Research\DetectResearchSignalsJob;
use App\Models\RankTrackingKeyword;
use App\Models\RankTrackingSnapshot;
use App\Models\Research\Keyword;
use App\Models\Research\KeywordAlert;
use App\Models\Research\KeywordIntelligence;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Services\Research\Intelligence\OpportunityEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DetectResearchSignalsTest extends TestCase
{
    use RefreshDatabase;

    private function makeKeyword(string $query): Keyword
    {
        return Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor($query), 'country' => 'us', 'language' => 'en'],
            ['query' => $query, 'normalized_query' => Keyword::normalize($query)]
        );
    }

    private function makeSnapshot(int $keywordId, array $domains, Carbon $when): SerpSnapshot
    {
        $snap = SerpSnapshot::create([
            'keyword_id' => $keywordId,
            'device' => 'desktop',
            'country' => 'us',
            'fetched_at' => $when,
            'fetched_on' => $when->copy()->startOfDay(),
        ]);
        foreach ($domains as $i => $domain) {
            SerpResult::create([
                'snapshot_id' => $snap->id,
                'rank' => $i + 1,
                'url' => "https://{$domain}/x",
                'domain' => $domain,
                'result_type' => 'organic',
            ]);
        }

        return $snap;
    }

    public function test_emits_ranking_drop_when_position_falls_seven_days_later(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $keyword = $this->makeKeyword('best running shoes');

        $rtk = RankTrackingKeyword::forceCreate([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'keyword' => $keyword->query,
            'keyword_hash' => hash('sha256', mb_strtolower($keyword->query).'|us|desktop'),
            'country' => 'us',
            'device' => 'desktop',
            'target_domain' => 'site.test',
            'check_interval_hours' => 24,
        ]);

        RankTrackingSnapshot::create([
            'rank_tracking_keyword_id' => $rtk->id,
            'checked_at' => Carbon::now()->subDays(8),
            'position' => 5,
        ]);
        RankTrackingSnapshot::create([
            'rank_tracking_keyword_id' => $rtk->id,
            'checked_at' => Carbon::now(),
            'position' => 14,
        ]);

        (new DetectResearchSignalsJob([$website->id]))->handle(app(OpportunityEngine::class));

        $alert = KeywordAlert::query()
            ->where('website_id', $website->id)
            ->where('type', KeywordAlert::TYPE_RANKING_DROP)
            ->first();
        $this->assertNotNull($alert);
        $this->assertSame(5, $alert->payload['from']);
        $this->assertSame(14, $alert->payload['to']);
    }

    public function test_emits_serp_change_when_jaccard_drops_below_half(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $keyword = $this->makeKeyword('matcha latte');

        SearchConsoleData::create([
            'website_id' => $website->id,
            'date' => Carbon::today()->subDay()->toDateString(),
            'query' => 'matcha latte',
            'page' => 'https://site.test/matcha',
            'clicks' => 1, 'impressions' => 10, 'position' => 8.0, 'ctr' => 0.1,
            'country' => 'USA', 'device' => '',
            'keyword_id' => $keyword->id,
        ]);

        $this->makeSnapshot($keyword->id, ['a.test', 'b.test', 'c.test', 'd.test', 'e.test'], Carbon::now()->subDays(2));
        $this->makeSnapshot($keyword->id, ['z.test', 'y.test', 'x.test', 'w.test', 'v.test'], Carbon::now());

        (new DetectResearchSignalsJob([$website->id]))->handle(app(OpportunityEngine::class));

        $this->assertTrue(KeywordAlert::query()
            ->where('website_id', $website->id)
            ->where('type', KeywordAlert::TYPE_SERP_CHANGE)
            ->where('keyword_id', $keyword->id)
            ->exists());
    }

    public function test_emits_volatility_spike_above_threshold(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $keyword = $this->makeKeyword('matcha latte');

        SearchConsoleData::create([
            'website_id' => $website->id,
            'date' => Carbon::today()->subDay()->toDateString(),
            'query' => 'matcha latte',
            'page' => 'https://site.test/matcha',
            'clicks' => 1, 'impressions' => 10, 'position' => 8.0, 'ctr' => 0.1,
            'country' => 'USA', 'device' => '',
            'keyword_id' => $keyword->id,
        ]);

        KeywordIntelligence::create([
            'keyword_id' => $keyword->id,
            'volatility_score' => 0.81,
        ]);

        (new DetectResearchSignalsJob([$website->id]))->handle(app(OpportunityEngine::class));

        $this->assertTrue(KeywordAlert::query()
            ->where('website_id', $website->id)
            ->where('type', KeywordAlert::TYPE_VOLATILITY_SPIKE)
            ->where('keyword_id', $keyword->id)
            ->exists());
    }

    public function test_dedupes_within_24h_window(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $keyword = $this->makeKeyword('matcha latte');

        SearchConsoleData::create([
            'website_id' => $website->id,
            'date' => Carbon::today()->subDay()->toDateString(),
            'query' => 'matcha latte',
            'page' => 'https://site.test/matcha',
            'clicks' => 1, 'impressions' => 10, 'position' => 8.0, 'ctr' => 0.1,
            'country' => 'USA', 'device' => '',
            'keyword_id' => $keyword->id,
        ]);
        KeywordIntelligence::create(['keyword_id' => $keyword->id, 'volatility_score' => 0.99]);

        $job = new DetectResearchSignalsJob([$website->id]);
        $job->handle(app(OpportunityEngine::class));
        $job->handle(app(OpportunityEngine::class));

        $this->assertSame(1, KeywordAlert::query()
            ->where('website_id', $website->id)
            ->where('type', KeywordAlert::TYPE_VOLATILITY_SPIKE)
            ->count());
    }
}
