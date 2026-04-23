<?php

namespace Tests\Feature;

use App\Models\KeywordMetric;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Services\ReportDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportDataServiceQuickWinsTest extends TestCase
{
    use RefreshDatabase;

    private function seedMetric(string $keyword, int $vol, float $comp, float $cpc): KeywordMetric
    {
        return KeywordMetric::create([
            'keyword' => $keyword,
            'keyword_hash' => KeywordMetric::hashKeyword($keyword),
            'country' => 'global',
            'data_source' => 'gkp',
            'search_volume' => $vol,
            'cpc' => $cpc,
            'currency' => 'USD',
            'competition' => $comp,
            'trend_12m' => null,
            'fetched_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(30),
        ]);
    }

    private function seedGsc(int $websiteId, string $query, string $page, float $position, int $impressions = 100): void
    {
        SearchConsoleData::create([
            'website_id' => $websiteId,
            // Two days ago — keeps us strictly inside the 90d window even when
            // SQLite DATE columns get stored as "YYYY-MM-DD 00:00:00" and a
            // whereBetween with an end-of-yesterday string would exclude them
            // via lex comparison.
            'date' => Carbon::now(config('app.timezone'))->subDays(2)->toDateString(),
            'query' => $query,
            'page' => $page,
            'clicks' => 1,
            'impressions' => $impressions,
            'position' => $position,
            'ctr' => 0.01,
            'country' => 'USA',
            'device' => '',
        ]);
    }

    public function test_quick_wins_excludes_high_competition_keywords(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', config('app.timezone')));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedMetric('low-comp kw', 1500, 0.3, 2.0);
        $this->seedMetric('high-comp kw', 3000, 0.85, 2.0);
        // Both keywords have GSC presence on this site (page-2 ranks).
        $this->seedGsc($website->id, 'low-comp kw', 'https://example.com/a', 14.0);
        $this->seedGsc($website->id, 'high-comp kw', 'https://example.com/b', 14.0);

        $out = app(ReportDataService::class)->quickWins($website->id, 20);

        $this->assertCount(1, $out);
        $this->assertSame('low-comp kw', $out[0]['keyword']);
    }

    public function test_quick_wins_excludes_low_volume_keywords(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', config('app.timezone')));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedMetric('above threshold', 600, 0.2, 2.0);
        $this->seedMetric('below threshold', 200, 0.2, 2.0);
        $this->seedGsc($website->id, 'above threshold', 'https://example.com/a', 14.0);
        $this->seedGsc($website->id, 'below threshold', 'https://example.com/b', 14.0);

        $out = app(ReportDataService::class)->quickWins($website->id, 20);

        $this->assertCount(1, $out);
        $this->assertSame('above threshold', $out[0]['keyword']);
    }

    public function test_quick_wins_excludes_keywords_already_ranking_top_10(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', config('app.timezone')));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedMetric('already ranked', 1000, 0.2, 2.0);
        $this->seedGsc($website->id, 'already ranked', 'https://example.com/ranked', 4.0);

        $out = app(ReportDataService::class)->quickWins($website->id, 20);

        $this->assertEmpty($out, 'A keyword the site already ranks top-10 for is not a quick win.');
    }

    public function test_quick_wins_excludes_keywords_with_no_gsc_presence_on_this_site(): void
    {
        // keyword_metrics is a global cache — every site shares it. Without
        // the GSC-presence gate, this test would surface "other site's data"
        // because the metric row has volume but the current site has no
        // signal at all that this query is relevant.
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', config('app.timezone')));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $otherSite = Website::factory()->create(['user_id' => $user->id]);

        // Metric exists in the shared cache (probably populated by another site's sync).
        $this->seedMetric('foreign keyword', 5000, 0.2, 3.0);
        // The OTHER site has GSC presence on it…
        $this->seedGsc($otherSite->id, 'foreign keyword', 'https://other.example/page', 12.0);

        // …but our current website has no signal for it. Don't suggest it.
        $out = app(ReportDataService::class)->quickWins($website->id, 20);

        $this->assertEmpty($out);
    }

    public function test_quick_wins_includes_keywords_ranking_outside_top_10(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', config('app.timezone')));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedMetric('page 2 ranker', 2000, 0.2, 3.0);
        $this->seedGsc($website->id, 'page 2 ranker', 'https://example.com/p2', 15.0);

        $out = app(ReportDataService::class)->quickWins($website->id, 20);

        $this->assertCount(1, $out);
        $this->assertSame('page 2 ranker', $out[0]['keyword']);
        $this->assertSame(15.0, $out[0]['current_position']);
        $this->assertSame('https://example.com/p2', $out[0]['current_page']);
    }

    public function test_quick_wins_sorts_by_upside_value_desc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', config('app.timezone')));
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        // Higher-value keyword (higher volume and higher CPC)
        $this->seedMetric('big upside', 5000, 0.2, 8.0);
        // Smaller-value keyword
        $this->seedMetric('small upside', 600, 0.2, 1.0);
        // Both must have GSC presence on the current site to qualify.
        $this->seedGsc($website->id, 'big upside', 'https://example.com/big', 14.0);
        $this->seedGsc($website->id, 'small upside', 'https://example.com/small', 14.0);

        $out = app(ReportDataService::class)->quickWins($website->id, 20);

        $this->assertCount(2, $out);
        $this->assertSame('big upside', $out[0]['keyword']);
        $this->assertGreaterThan($out[1]['upside_value'], $out[0]['upside_value']);
    }
}
