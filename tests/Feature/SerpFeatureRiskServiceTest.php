<?php

namespace Tests\Feature;

use App\Models\RankTrackingKeyword;
use App\Models\RankTrackingSnapshot;
use App\Models\User;
use App\Models\Website;
use App\Services\SerpFeatureRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SerpFeatureRiskServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_flags_at_risk_when_competitor_owns_feature_and_we_do_not_own_top(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $kw = RankTrackingKeyword::create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'keyword' => 'how to seo',
            'keyword_hash' => hash('sha256', 'how to seo|google|organic|us|en||desktop'),
            'target_domain' => $website->domain,
            'search_engine' => 'google', 'search_type' => 'organic',
            'country' => 'us', 'language' => 'en', 'device' => 'desktop',
            'depth' => 100, 'autocorrect' => true, 'safe_search' => false,
            'check_interval_hours' => 12, 'is_active' => true,
        ]);

        RankTrackingSnapshot::create([
            'rank_tracking_keyword_id' => $kw->id,
            'checked_at' => Carbon::now(),
            'position' => 4,
            'url' => 'https://example.com/a',
            'status' => 'ok',
            'forced' => false,
            'serp_features' => ['answerBox', 'peopleAlsoAsk'],
        ]);

        $out = app(SerpFeatureRiskService::class)->riskFor($kw);
        $this->assertTrue($out['at_risk']);
        $this->assertContains('answerBox', $out['features_present']);
    }

    public function test_not_at_risk_when_we_own_top_result(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $kw = RankTrackingKeyword::create([
            'website_id' => $website->id, 'user_id' => $user->id,
            'keyword' => 'k', 'keyword_hash' => hash('sha256', 'k2'),
            'target_domain' => $website->domain,
            'search_engine' => 'google', 'search_type' => 'organic',
            'country' => 'us', 'language' => 'en', 'device' => 'desktop',
            'depth' => 100, 'autocorrect' => true, 'safe_search' => false,
            'check_interval_hours' => 12, 'is_active' => true,
        ]);

        RankTrackingSnapshot::create([
            'rank_tracking_keyword_id' => $kw->id,
            'checked_at' => Carbon::now(),
            'position' => 1,
            'status' => 'ok', 'forced' => false,
            'serp_features' => ['answerBox'],
        ]);

        $out = app(SerpFeatureRiskService::class)->riskFor($kw);
        $this->assertFalse($out['at_risk']);
    }

    public function test_detects_lost_feature_between_snapshots(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $kw = RankTrackingKeyword::create([
            'website_id' => $website->id, 'user_id' => $user->id,
            'keyword' => 'k', 'keyword_hash' => hash('sha256', 'k3'),
            'target_domain' => $website->domain,
            'search_engine' => 'google', 'search_type' => 'organic',
            'country' => 'us', 'language' => 'en', 'device' => 'desktop',
            'depth' => 100, 'autocorrect' => true, 'safe_search' => false,
            'check_interval_hours' => 12, 'is_active' => true,
        ]);

        RankTrackingSnapshot::create([
            'rank_tracking_keyword_id' => $kw->id,
            'checked_at' => Carbon::now()->subDay(),
            'position' => 3, 'status' => 'ok', 'forced' => false,
            'serp_features' => ['answerBox', 'topStories'],
        ]);
        RankTrackingSnapshot::create([
            'rank_tracking_keyword_id' => $kw->id,
            'checked_at' => Carbon::now(),
            'position' => 3, 'status' => 'ok', 'forced' => false,
            'serp_features' => ['topStories'],
        ]);

        $out = app(SerpFeatureRiskService::class)->riskFor($kw);
        $this->assertTrue($out['lost_feature']);
        $this->assertContains('answerBox', $out['features_lost']);
    }
}
