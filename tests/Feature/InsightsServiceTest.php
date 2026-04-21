<?php

namespace Tests\Feature;

use App\Models\PageIndexingStatus;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Services\ReportDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cannibalization_flags_query_split_across_pages(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20', 'UTC'));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $date = '2026-04-10';
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => $date, 'query' => 'best seo tools',
            'page' => 'https://'.$website->domain.'/a', 'clicks' => 40, 'impressions' => 800,
            'position' => 6.0, 'ctr' => 0.05, 'country' => '', 'device' => '',
        ]);
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => $date, 'query' => 'best seo tools',
            'page' => 'https://'.$website->domain.'/b', 'clicks' => 20, 'impressions' => 400,
            'position' => 11.0, 'ctr' => 0.05, 'country' => '', 'device' => '',
        ]);
        // Control query — single page, should NOT appear
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => $date, 'query' => 'uncontested',
            'page' => 'https://'.$website->domain.'/c', 'clicks' => 200, 'impressions' => 2000,
            'position' => 3.0, 'ctr' => 0.1, 'country' => '', 'device' => '',
        ]);

        $out = app(ReportDataService::class)->cannibalizationReport($website->id);

        $this->assertCount(1, $out);
        $this->assertSame('best seo tools', $out[0]['query']);
        $this->assertSame('https://'.$website->domain.'/a', $out[0]['primary_page']);
        $this->assertSame(2, $out[0]['page_count']);
        $this->assertCount(1, $out[0]['competing_pages']);
    }

    public function test_striking_distance_returns_position_5_to_20_high_impression_queries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20', 'UTC'));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        // Qualifies: position 11, 2000 impressions, low CTR
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => '2026-04-10', 'query' => 'almost page 1',
            'page' => 'https://x/y', 'clicks' => 20, 'impressions' => 2000,
            'position' => 11.0, 'ctr' => 0.01, 'country' => '', 'device' => '',
        ]);
        // Does not qualify: too high a position
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => '2026-04-10', 'query' => 'already ranking',
            'page' => 'https://x/z', 'clicks' => 200, 'impressions' => 2000,
            'position' => 2.0, 'ctr' => 0.1, 'country' => '', 'device' => '',
        ]);
        // Does not qualify: not enough impressions
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => '2026-04-10', 'query' => 'too small',
            'page' => 'https://x/w', 'clicks' => 1, 'impressions' => 50,
            'position' => 12.0, 'ctr' => 0.02, 'country' => '', 'device' => '',
        ]);

        $out = app(ReportDataService::class)->strikingDistance($website->id);

        $this->assertCount(1, $out);
        $this->assertSame('almost page 1', $out[0]['query']);
    }

    public function test_indexing_fails_with_traffic_surfaces_only_failing_pages_with_recent_impressions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20', 'UTC'));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $failingPage = 'https://'.$website->domain.'/broken';
        $passingPage = 'https://'.$website->domain.'/ok';
        $quietFailingPage = 'https://'.$website->domain.'/nobody-visits';

        PageIndexingStatus::create([
            'website_id' => $website->id, 'page' => $failingPage, 'google_verdict' => 'FAIL',
            'google_coverage_state' => 'Crawled — currently not indexed',
        ]);
        PageIndexingStatus::create([
            'website_id' => $website->id, 'page' => $passingPage, 'google_verdict' => 'PASS',
            'google_coverage_state' => 'Submitted and indexed',
        ]);
        PageIndexingStatus::create([
            'website_id' => $website->id, 'page' => $quietFailingPage, 'google_verdict' => 'FAIL',
            'google_coverage_state' => 'Discovered — currently not indexed',
        ]);

        // Traffic within the 14d window for failing + passing, none for quiet-failing
        foreach (['2026-04-15', '2026-04-16'] as $date) {
            SearchConsoleData::create([
                'website_id' => $website->id, 'date' => $date, 'query' => 'x',
                'page' => $failingPage, 'clicks' => 3, 'impressions' => 50, 'position' => 15.0,
                'ctr' => 0.06, 'country' => '', 'device' => '',
            ]);
            SearchConsoleData::create([
                'website_id' => $website->id, 'date' => $date, 'query' => 'x',
                'page' => $passingPage, 'clicks' => 10, 'impressions' => 120, 'position' => 4.0,
                'ctr' => 0.08, 'country' => '', 'device' => '',
            ]);
        }

        $out = app(ReportDataService::class)->indexingFailsWithTraffic($website->id);

        $this->assertCount(1, $out);
        $this->assertSame($failingPage, $out[0]['page']);
        $this->assertSame('FAIL', $out[0]['verdict']);
        $this->assertSame(100, $out[0]['recent_impressions']);
    }
}
