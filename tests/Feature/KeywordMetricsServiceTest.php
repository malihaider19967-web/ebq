<?php

namespace Tests\Feature;

use App\Jobs\FetchKeywordMetricsJob;
use App\Models\KeywordMetric;
use App\Services\KeywordMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KeywordMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.keywords_everywhere.key' => 'test-key']);
        config(['services.keywords_everywhere.base_url' => 'https://api.keywordseverywhere.com']);
        config(['services.keywords_everywhere.fresh_days' => 30]);
    }

    public function test_fresh_rows_are_returned_without_api_call(): void
    {
        Http::fake();
        Queue::fake();

        KeywordMetric::create([
            'keyword' => 'saas seo audit',
            'keyword_hash' => KeywordMetric::hashKeyword('saas seo audit'),
            'country' => 'global',
            'data_source' => 'gkp',
            'search_volume' => 2400,
            'cpc' => 1.2300,
            'currency' => 'USD',
            'competition' => 0.5600,
            'trend_12m' => null,
            'fetched_at' => Carbon::now()->subDays(2),
            'expires_at' => Carbon::now()->addDays(28),
        ]);

        $service = app(KeywordMetricsService::class);
        $rows = $service->metricsOrQueue(['saas seo audit'], 'global');

        $this->assertCount(1, $rows);
        $this->assertSame(2400, $rows[KeywordMetric::hashKeyword('saas seo audit')]->search_volume);
        Http::assertNothingSent();
        Queue::assertNotPushed(FetchKeywordMetricsJob::class);
    }

    public function test_missing_keywords_trigger_background_fetch(): void
    {
        Queue::fake();
        Http::fake();

        app(KeywordMetricsService::class)->metricsOrQueue(['unseen keyword'], 'global');

        Queue::assertPushed(FetchKeywordMetricsJob::class, function ($job) {
            return in_array('unseen keyword', $job->keywords, true) && $job->country === 'global';
        });
    }

    public function test_stale_rows_trigger_background_fetch(): void
    {
        Queue::fake();

        KeywordMetric::create([
            'keyword' => 'stale term',
            'keyword_hash' => KeywordMetric::hashKeyword('stale term'),
            'country' => 'global',
            'data_source' => 'gkp',
            'search_volume' => 1000,
            'fetched_at' => Carbon::now()->subDays(45),
            'expires_at' => Carbon::now()->subDays(15),
        ]);

        app(KeywordMetricsService::class)->metricsOrQueue(['stale term'], 'global');

        Queue::assertPushed(FetchKeywordMetricsJob::class, 1);
    }

    public function test_refresh_upserts_parsed_response(): void
    {
        Http::fake([
            '*keywordseverywhere.com*' => Http::response([
                'data' => [
                    [
                        'keyword' => 'best seo tool',
                        'vol' => 18100,
                        'cpc' => ['value' => 12.34, 'currency' => 'USD'],
                        'competition' => 0.71,
                        'trend' => [['month' => 'January', 'year' => 2026, 'value' => 14000]],
                    ],
                ],
                'credits' => 8912,
            ], 200),
        ]);

        $written = app(KeywordMetricsService::class)->refresh(['best seo tool'], 'global');

        $this->assertSame(1, $written);
        $row = KeywordMetric::where('keyword_hash', KeywordMetric::hashKeyword('best seo tool'))->first();
        $this->assertNotNull($row);
        $this->assertSame(18100, $row->search_volume);
        $this->assertEqualsWithDelta(12.34, (float) $row->cpc, 0.0001);
        $this->assertSame('USD', $row->currency);
        $this->assertEqualsWithDelta(0.71, (float) $row->competition, 0.0001);
        $this->assertIsArray($row->trend_12m);
        $this->assertTrue($row->isFresh());
    }

    public function test_refresh_is_idempotent_on_repeat_calls(): void
    {
        Http::fake([
            '*keywordseverywhere.com*' => Http::response([
                'data' => [['keyword' => 'repeat', 'vol' => 500, 'cpc' => ['value' => 0.9, 'currency' => 'USD'], 'competition' => 0.3]],
                'credits' => 0,
            ], 200),
        ]);

        app(KeywordMetricsService::class)->refresh(['repeat'], 'global');
        app(KeywordMetricsService::class)->refresh(['repeat'], 'global');

        $this->assertSame(1, KeywordMetric::where('keyword_hash', KeywordMetric::hashKeyword('repeat'))->count());
    }

    public function test_missing_api_key_short_circuits_cleanly(): void
    {
        config(['services.keywords_everywhere.key' => '']);
        Http::fake();

        $written = app(KeywordMetricsService::class)->refresh(['anything'], 'global');

        $this->assertSame(0, $written);
        Http::assertNothingSent();
    }
}
