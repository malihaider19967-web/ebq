<?php

namespace Tests\Feature;

use App\Jobs\FetchKeywordMetricsJob;
use App\Models\KeywordMetric;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FetchKeywordMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedQuery(int $websiteId, string $query, int $impressions, int $daysAgo = 1): void
    {
        SearchConsoleData::create([
            'website_id' => $websiteId,
            'date' => Carbon::today()->subDays($daysAgo)->toDateString(),
            'query' => $query,
            'page' => 'https://example.com/a',
            'clicks' => 1,
            'impressions' => $impressions,
            'position' => 7.5,
            'ctr' => 0.01,
            'country' => 'USA',
            'device' => '',
        ]);
    }

    public function test_only_queries_above_threshold_are_queued(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedQuery($website->id, 'low-impr-query', 50);
        $this->seedQuery($website->id, 'mid-impr-query', 120);
        $this->seedQuery($website->id, 'high-impr-query', 800);

        $this->artisan('ebq:fetch-keyword-metrics', ['--min-impressions' => 100])
            ->assertSuccessful();

        Queue::assertPushed(FetchKeywordMetricsJob::class, function (FetchKeywordMetricsJob $job) {
            return in_array('mid-impr-query', $job->keywords, true)
                && in_array('high-impr-query', $job->keywords, true)
                && ! in_array('low-impr-query', $job->keywords, true);
        });
    }

    public function test_fresh_rows_are_skipped(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedQuery($website->id, 'cached-query', 500);
        $this->seedQuery($website->id, 'uncached-query', 500);

        KeywordMetric::create([
            'keyword' => 'cached-query',
            'keyword_hash' => KeywordMetric::hashKeyword('cached-query'),
            'country' => 'global',
            'data_source' => 'gkp',
            'search_volume' => 1000,
            'fetched_at' => Carbon::now()->subDay(),
            'expires_at' => Carbon::now()->addDays(29),
        ]);

        $this->artisan('ebq:fetch-keyword-metrics')->assertSuccessful();

        Queue::assertPushed(FetchKeywordMetricsJob::class, function (FetchKeywordMetricsJob $job) {
            return in_array('uncached-query', $job->keywords, true)
                && ! in_array('cached-query', $job->keywords, true);
        });
    }

    public function test_force_refetches_fresh_rows(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedQuery($website->id, 'force-me', 500);

        KeywordMetric::create([
            'keyword' => 'force-me',
            'keyword_hash' => KeywordMetric::hashKeyword('force-me'),
            'country' => 'global',
            'data_source' => 'gkp',
            'search_volume' => 999,
            'fetched_at' => Carbon::now()->subDay(),
            'expires_at' => Carbon::now()->addDays(29),
        ]);

        $this->artisan('ebq:fetch-keyword-metrics', ['--force' => true])->assertSuccessful();

        Queue::assertPushed(FetchKeywordMetricsJob::class, fn ($job) => in_array('force-me', $job->keywords, true));
    }

    public function test_dry_run_does_not_dispatch(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $this->seedQuery($website->id, 'dry-query', 500);

        $this->artisan('ebq:fetch-keyword-metrics', ['--dry-run' => true])->assertSuccessful();

        Queue::assertNotPushed(FetchKeywordMetricsJob::class);
    }
}
