<?php

namespace Tests\Feature\Competitive;

use App\Exceptions\QuotaExceededException;
use App\Services\Competitive\OpportunityScoreService;
use App\Services\SerperSearchClient;
use App\Support\KeywordFinderLocations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class OpportunityScoreLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_serper_gl_mapping(): void
    {
        $this->assertSame('gb', KeywordFinderLocations::serperGl('uk'));
        $this->assertSame('us', KeywordFinderLocations::serperGl('global'));
        $this->assertSame('us', KeywordFinderLocations::serperGl(''));
        $this->assertSame('us', KeywordFinderLocations::serperGl(null));
        $this->assertSame('de', KeywordFinderLocations::serperGl('de'));
    }

    private function mockSerper(callable $fn): void
    {
        $serper = Mockery::mock(SerperSearchClient::class);
        $serper->shouldReceive('query')->andReturnUsing($fn);
        $this->app->instance(SerperSearchClient::class, $serper);
    }

    public function test_live_score_rethrows_quota_exception(): void
    {
        $this->mockSerper(function () {
            throw new QuotaExceededException('serp_api', 100, 100, 'Limit reached', 'https://app/upgrade');
        });

        $this->expectException(QuotaExceededException::class);
        app(OpportunityScoreService::class)->liveScore('kw', 'us', 1000, null, 1, 1);
    }

    public function test_live_score_returns_null_on_no_data(): void
    {
        $this->mockSerper(fn () => null);

        $this->assertNull(app(OpportunityScoreService::class)->liveScore('kw', 'us', 1000, null, 1, 1));
    }

    public function test_live_score_returns_score_array_on_valid_serp(): void
    {
        $this->mockSerper(fn () => [
            'organic' => [['link' => 'https://www.rival.com/', 'position' => 1]],
            'answerBox' => ['x' => 1],
        ]);

        $result = app(OpportunityScoreService::class)->liveScore('kw', 'us', 5000, 8.0, 1, 1);
        $this->assertIsInt($result['score']);
        $this->assertArrayHasKey('components', $result);
    }
}
