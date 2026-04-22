<?php

namespace Tests\Feature;

use App\Models\PageIndexingStatus;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Services\PluginInsightResolver;
use App\Services\ReportDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PerCountryInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedQuery(int $websiteId, string $url, string $country, int $impressions, float $position, int $clicks = 1, string $query = 'striking distance'): void
    {
        for ($i = 0; $i < 20; $i++) {
            SearchConsoleData::create([
                'website_id' => $websiteId,
                'date' => Carbon::parse('2026-04-21')->subDays($i)->toDateString(),
                'query' => $query,
                'page' => $url,
                'clicks' => $clicks,
                'impressions' => (int) ($impressions / 20),
                'position' => $position,
                'ctr' => $clicks / max(1, $impressions / 20) / 100,
                'country' => $country,
                'device' => '',
            ]);
        }
    }

    public function test_striking_distance_filters_to_us_only_when_country_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', 'UTC'));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $url = 'https://example.com/article';

        $this->seedQuery($website->id, $url, 'US', 2000, 11.0, 1, 'query-in-us');
        $this->seedQuery($website->id, $url, 'IN', 2000, 11.0, 1, 'query-in-india');

        $svc = app(ReportDataService::class);

        $all = $svc->strikingDistance($website->id);
        $us = $svc->strikingDistance($website->id, null, null, 50, 'US');
        $in = $svc->strikingDistance($website->id, null, null, 50, 'IN');

        $this->assertCount(2, $all, 'all-country call should include both queries');
        $this->assertCount(1, $us);
        $this->assertSame('query-in-us', $us[0]['query']);
        $this->assertCount(1, $in);
        $this->assertSame('query-in-india', $in[0]['query']);
    }

    public function test_cannibalization_report_filters_by_country(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);

        // Only in the US: two pages share the query "best seo"
        $this->seedQuery($website->id, 'https://example.com/a', 'US', 400, 5.0, 25, 'best seo');
        $this->seedQuery($website->id, 'https://example.com/b', 'US', 400, 9.0, 15, 'best seo');

        // In IN, only one page ranks for "best seo" — no cannibalization there
        $this->seedQuery($website->id, 'https://example.com/a', 'IN', 400, 5.0, 25, 'best seo');

        $svc = app(ReportDataService::class);

        $usReport = $svc->cannibalizationReport($website->id, null, null, 50, 'US');
        $inReport = $svc->cannibalizationReport($website->id, null, null, 50, 'IN');

        $this->assertNotEmpty($usReport, 'US should see cannibalization');
        $this->assertEmpty($inReport, 'IN should see no cannibalization');
    }

    public function test_indexing_fails_with_traffic_respects_country_filter(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', 'UTC'));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $failingUrl = 'https://example.com/broken';

        PageIndexingStatus::create([
            'website_id' => $website->id,
            'page' => $failingUrl,
            'google_verdict' => 'FAIL',
            'google_coverage_state' => 'Crawled — currently not indexed',
        ]);

        // Only IN has impressions in the last 14 days
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => '2026-04-20', 'query' => 'x',
            'page' => $failingUrl, 'clicks' => 0, 'impressions' => 50,
            'position' => 15.0, 'ctr' => 0.0, 'country' => 'IN', 'device' => '',
        ]);

        $svc = app(ReportDataService::class);

        $usOnly = $svc->indexingFailsWithTraffic($website->id, 14, 50, 'US');
        $inOnly = $svc->indexingFailsWithTraffic($website->id, 14, 50, 'IN');
        $all = $svc->indexingFailsWithTraffic($website->id, 14, 50, null);

        $this->assertEmpty($usOnly, 'No US impressions → empty');
        $this->assertCount(1, $inOnly, 'IN impressions → 1');
        $this->assertCount(1, $all, 'Aggregate → 1');
    }

    public function test_insight_counts_cascades_country_filter(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', 'UTC'));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);

        $this->seedQuery($website->id, 'https://example.com/a', 'US', 2000, 11.0, 1, 'query-us');

        $svc = app(ReportDataService::class);
        $usCounts = $svc->insightCounts($website->id, 'US');
        $frCounts = $svc->insightCounts($website->id, 'FR');

        $this->assertSame(1, $usCounts['striking_distance']);
        $this->assertSame(0, $frCounts['striking_distance']);
    }

    public function test_top_countries_trend_orders_by_clicks_desc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', 'UTC'));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $url = 'https://example.com/p';

        // US: 400 clicks current, 200 previous
        for ($i = 0; $i < 25; $i++) {
            SearchConsoleData::create([
                'website_id' => $website->id,
                'date' => Carbon::parse('2026-04-21')->subDays($i)->toDateString(),
                'query' => 'q', 'page' => $url,
                'clicks' => 16, 'impressions' => 100, 'position' => 3, 'ctr' => 0.16,
                'country' => 'US', 'device' => '',
            ]);
        }
        // IN: 200 clicks current
        for ($i = 0; $i < 25; $i++) {
            SearchConsoleData::create([
                'website_id' => $website->id,
                'date' => Carbon::parse('2026-04-21')->subDays($i)->toDateString(),
                'query' => 'q', 'page' => $url,
                'clicks' => 8, 'impressions' => 60, 'position' => 6, 'ctr' => 0.13,
                'country' => 'IN', 'device' => '',
            ]);
        }

        $top = app(ReportDataService::class)->topCountriesTrend($website->id, 10);
        $this->assertNotEmpty($top);
        $this->assertSame('US', $top[0]['country']);
        $this->assertSame('IN', $top[1]['country']);
        $this->assertGreaterThan($top[1]['clicks'], $top[0]['clicks']);
    }

    public function test_country_breakdown_returns_per_country_totals_for_a_url(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', 'UTC'));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $url = 'https://example.com/multi-market';

        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => '2026-04-20', 'query' => 'q',
            'page' => $url, 'clicks' => 50, 'impressions' => 1000, 'position' => 3.0,
            'ctr' => 0.05, 'country' => 'US', 'device' => '',
        ]);
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => '2026-04-20', 'query' => 'q',
            'page' => $url, 'clicks' => 10, 'impressions' => 300, 'position' => 11.0,
            'ctr' => 0.033, 'country' => 'GB', 'device' => '',
        ]);

        $out = app(PluginInsightResolver::class)->countryBreakdown($website, $url);

        $this->assertCount(2, $out['by_country']);
        $this->assertSame('US', $out['by_country'][0]['country']);
        $this->assertSame(50, $out['by_country'][0]['clicks']);
        $this->assertSame('GB', $out['by_country'][1]['country']);
    }
}
