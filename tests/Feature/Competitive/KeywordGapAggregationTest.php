<?php

namespace Tests\Feature\Competitive;

use App\Models\KeywordApiRequest;
use App\Models\KeywordGapAnalysis;
use App\Models\KeywordGapRow;
use App\Models\KeywordMetric;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Services\Competitive\KeywordGapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KeywordGapAggregationTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<string> $keywords */
    private function completedIdeas(array $keywords): string
    {
        $id = (string) Str::uuid();
        KeywordApiRequest::create([
            'request_id' => $id,
            'type' => 'ideas',
            'mode' => 'website',
            'status' => 'completed',
            'result' => ['results' => array_map(fn ($k) => ['keyword' => $k, 'avgMonthlySearches' => 1000], $keywords)],
        ]);

        return $id;
    }

    private function seedMetric(string $keyword, int $volume): void
    {
        KeywordMetric::create([
            'keyword' => $keyword,
            'keyword_hash' => KeywordMetric::hashKeyword($keyword),
            'country' => 'us',
            'data_source' => 'gkp',
            'search_volume' => $volume,
            'fetched_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function gsc(Website $w, string $query, float $position): void
    {
        SearchConsoleData::create([
            'website_id' => $w->id,
            'date' => now()->subDays(3)->toDateString(),
            'query' => $query,
            'page' => 'https://mysite.com/',
            'clicks' => 1, 'impressions' => 100, 'ctr' => 0.01, 'position' => $position,
            'country' => 'usa', 'device' => 'DESKTOP',
        ]);
    }

    private function makeAnalysis(Website $w, string $ourId, string $compId): KeywordGapAnalysis
    {
        return KeywordGapAnalysis::create([
            'website_id' => $w->id,
            'user_id' => $w->user_id,
            'our_url' => 'mysite.com',
            'competitor_urls' => ['rival.com'],
            'country' => 'us',
            'status' => 'collecting',
            'request_ids' => [
                ['id' => $ourId, 'role' => 'ours', 'url' => 'mysite.com', 'domain' => 'mysite.com'],
                ['id' => $compId, 'role' => 'competitor', 'url' => 'rival.com', 'domain' => 'rival.com'],
            ],
            'total_requests' => 2,
            'completed_requests' => 0,
        ]);
    }

    public function test_buckets_with_gsc_positions(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withGscOnly()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);

        $ourId = $this->completedIdeas(['shared kw', 'our strong kw']);
        $compId = $this->completedIdeas(['shared kw', 'missing kw']);
        foreach (['shared kw', 'our strong kw', 'missing kw'] as $i => $kw) {
            $this->seedMetric($kw, (3 - $i) * 1000);
        }
        $this->gsc($website, 'shared kw', 15.0);     // we rank poorly → weak
        $this->gsc($website, 'our strong kw', 3.0);  // we rank well → strength

        $analysis = $this->makeAnalysis($website, $ourId, $compId);
        app(KeywordGapService::class)->maybeAggregate($analysis);
        $analysis->refresh();

        $this->assertSame('completed', $analysis->status);
        $this->assertSame('missing', KeywordGapRow::where('keyword', 'missing kw')->value('bucket'));
        $this->assertSame('weak', KeywordGapRow::where('keyword', 'shared kw')->value('bucket'));
        $this->assertSame('strength', KeywordGapRow::where('keyword', 'our strong kw')->value('bucket'));
        $this->assertSame(1, $analysis->summary['missing']);
        $this->assertSame(1, $analysis->summary['weak']);
        $this->assertSame(1, $analysis->summary['strength']);
    }

    public function test_aggregation_fires_exactly_once(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withGscOnly()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);
        $ourId = $this->completedIdeas(['kw a']);
        $compId = $this->completedIdeas(['kw a', 'kw b']);
        $this->seedMetric('kw a', 1000);
        $this->seedMetric('kw b', 500);

        $analysis = $this->makeAnalysis($website, $ourId, $compId);
        $service = app(KeywordGapService::class);
        $service->maybeAggregate($analysis);
        $countAfterFirst = KeywordGapRow::where('keyword_gap_analysis_id', $analysis->id)->count();

        $service->maybeAggregate($analysis->fresh()); // second poll — must be a no-op
        $this->assertSame($countAfterFirst, KeywordGapRow::where('keyword_gap_analysis_id', $analysis->id)->count());
    }

    public function test_no_gsc_collapses_to_shared_bucket(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withNoSources()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);
        $ourId = $this->completedIdeas(['shared kw']);
        $compId = $this->completedIdeas(['shared kw', 'missing kw']);
        $this->seedMetric('shared kw', 1000);
        $this->seedMetric('missing kw', 900);

        $analysis = $this->makeAnalysis($website, $ourId, $compId);
        app(KeywordGapService::class)->maybeAggregate($analysis);

        $this->assertSame('shared', KeywordGapRow::where('keyword', 'shared kw')->value('bucket'));
        $this->assertSame('missing', KeywordGapRow::where('keyword', 'missing kw')->value('bucket'));
        $this->assertSame(0, KeywordGapRow::where('bucket', 'weak')->count());
        $this->assertSame(0, KeywordGapRow::where('bucket', 'strength')->count());
    }

    public function test_timeout_without_our_data_fails(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withNoSources()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);

        // Competitor finished, our request still running — and no GSC fallback.
        $ourId = (string) Str::uuid();
        KeywordApiRequest::create(['request_id' => $ourId, 'type' => 'ideas', 'mode' => 'website', 'status' => 'running']);
        $compId = $this->completedIdeas(['x']);

        $analysis = $this->makeAnalysis($website, $ourId, $compId);
        KeywordGapAnalysis::where('id', $analysis->id)->update(['created_at' => now()->subMinutes(10)]);

        app(KeywordGapService::class)->maybeAggregate($analysis->fresh());
        $this->assertSame('failed', $analysis->fresh()->status);
    }

    public function test_row_cap_keeps_top_by_volume(): void
    {
        config(['services.competitive.gap_row_cap' => 1]);
        $user = User::factory()->create();
        $website = Website::factory()->withNoSources()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);

        $ourId = $this->completedIdeas([]);
        $compId = $this->completedIdeas(['low vol', 'high vol']);
        $this->seedMetric('low vol', 10);
        $this->seedMetric('high vol', 99999);

        $analysis = $this->makeAnalysis($website, $ourId, $compId);
        app(KeywordGapService::class)->maybeAggregate($analysis);

        $rows = KeywordGapRow::where('keyword_gap_analysis_id', $analysis->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('high vol', $rows->first()->keyword);
    }
}
