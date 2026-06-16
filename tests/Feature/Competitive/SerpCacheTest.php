<?php

namespace Tests\Feature\Competitive;

use App\Services\Competitive\SerpCache;
use App\Services\SerperSearchClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SerpCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // suppress DA-enrichment side dispatches
    }

    public function test_second_lookup_is_served_from_cache_across_clients(): void
    {
        $serper = Mockery::mock(SerperSearchClient::class);
        // Live call happens exactly once even though two different clients ask.
        $serper->shouldReceive('query')->once()->andReturn([
            'organic' => [['link' => 'https://www.rival.com/x', 'position' => 1]],
            'answerBox' => ['a' => 1],
        ]);
        $this->app->instance(SerperSearchClient::class, $serper);

        $cache = app(SerpCache::class);
        $first = $cache->organic('best crm', 'us', websiteId: 1, ownerUserId: 1);
        $second = $cache->organic('best crm', 'us', websiteId: 999, ownerUserId: 2); // different client

        $this->assertSame('rival.com', $first['organic'][0]['domain']);
        $this->assertSame(1, $first['organic'][0]['position']);
        $this->assertTrue($first['answerBox']);
        $this->assertEquals($first, $second);
        $this->assertDatabaseCount('serp_cache', 1);
    }

    public function test_distinct_country_is_a_distinct_cache_entry(): void
    {
        $serper = Mockery::mock(SerperSearchClient::class);
        $serper->shouldReceive('query')->twice()->andReturn(['organic' => [['link' => 'https://x.com/', 'position' => 1]]]);
        $this->app->instance(SerperSearchClient::class, $serper);

        $cache = app(SerpCache::class);
        $cache->organic('seo tools', 'us');
        $cache->organic('seo tools', 'gb');

        $this->assertDatabaseCount('serp_cache', 2);
    }

    public function test_stale_entry_is_refetched(): void
    {
        $serper = Mockery::mock(SerperSearchClient::class);
        $serper->shouldReceive('query')->twice()->andReturn(['organic' => [['link' => 'https://x.com/', 'position' => 1]]]);
        $this->app->instance(SerperSearchClient::class, $serper);

        config(['services.competitive.serp_cache_days' => 7]);
        $cache = app(SerpCache::class);
        $cache->organic('link building', 'us');

        // Expire the cached row.
        \App\Models\SerpCacheEntry::query()->update(['expires_at' => now()->subDay()]);
        $cache->organic('link building', 'us'); // miss → second live call

        $this->assertDatabaseCount('serp_cache', 1); // updateOrCreate keeps one row
    }
}
