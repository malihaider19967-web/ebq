<?php

namespace Tests\Feature\Api\V1;

use App\Models\RankTrackingKeyword;
use App\Models\RankTrackingSnapshot;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SeoSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_focus_keyword_suggestions_ranks_by_opportunity_score(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', 'UTC'));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $url = 'https://example.com/post-42';

        // High-impression, mid-position, low-CTR query — should top the list.
        for ($i = 0; $i < 20; $i++) {
            SearchConsoleData::create([
                'website_id' => $website->id,
                'date' => Carbon::parse('2026-04-21')->subDays($i)->toDateString(),
                'query' => 'striking opportunity',
                'page' => $url,
                'clicks' => 1, 'impressions' => 200,
                'position' => 12.0, 'ctr' => 0.005,
                'country' => '', 'device' => '',
            ]);
        }
        // Mediocre query — position 25, fewer impressions.
        for ($i = 0; $i < 10; $i++) {
            SearchConsoleData::create([
                'website_id' => $website->id,
                'date' => Carbon::parse('2026-04-21')->subDays($i)->toDateString(),
                'query' => 'deeper query',
                'page' => $url,
                'clicks' => 0, 'impressions' => 40,
                'position' => 25.0, 'ctr' => 0.0,
                'country' => '', 'device' => '',
            ]);
        }

        $plain = $website->createToken('t', ['read:insights'])->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/posts/42/focus-keyword-suggestions?url='.urlencode($url))
            ->assertOk();

        $suggestions = $response->json('suggestions');
        $this->assertNotEmpty($suggestions);
        $this->assertSame('striking opportunity', $suggestions[0]['query']);
        $this->assertGreaterThan($suggestions[1]['opportunity_score'] ?? -999, $suggestions[0]['opportunity_score']);
    }

    public function test_serp_preview_returns_competitor_top_results_for_tracked_keyword(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $kw = RankTrackingKeyword::create([
            'website_id' => $website->id, 'user_id' => $user->id,
            'keyword' => 'how to seo',
            'keyword_hash' => hash('sha256', 'how to seo|test'),
            'target_domain' => $website->domain,
            'search_engine' => 'google', 'search_type' => 'organic',
            'country' => 'us', 'language' => 'en', 'device' => 'desktop',
            'depth' => 100, 'autocorrect' => true, 'safe_search' => false,
            'check_interval_hours' => 12, 'is_active' => true,
        ]);
        RankTrackingSnapshot::create([
            'rank_tracking_keyword_id' => $kw->id,
            'checked_at' => Carbon::now(),
            'position' => 7,
            'status' => 'ok', 'forced' => false,
            'top_results' => [
                ['position' => 1, 'title' => 'Guide A', 'link' => 'https://comp1.com/a', 'snippet' => 'A'],
                ['position' => 2, 'title' => 'Guide B', 'link' => 'https://comp2.com/b', 'snippet' => 'B'],
                ['position' => 3, 'title' => 'Guide C', 'link' => 'https://comp3.com/c', 'snippet' => 'C'],
            ],
        ]);

        $plain = $website->createToken('t', ['read:insights'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/posts/1/serp-preview?query=how+to+seo')
            ->assertOk()
            ->assertJson([
                'matched' => true,
                'query' => 'how to seo',
            ]);

        $this->assertCount(3, $response->json('results'));
        $this->assertSame('Guide A', $response->json('results.0.title'));
    }

    public function test_serp_preview_gracefully_returns_unmatched_when_keyword_is_untracked(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $plain = $website->createToken('t', ['read:insights'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/posts/1/serp-preview?query=nothing+tracked')
            ->assertOk()
            ->assertJson([
                'matched' => false,
                'query' => 'nothing tracked',
                'results' => [],
            ]);
    }
}
