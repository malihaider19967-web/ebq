<?php

namespace Tests\Feature;

use App\Jobs\CrawlPageBatchJob;
use App\Jobs\CrawlPassJob;
use App\Jobs\CrawlSitemapDeltaJob;
use App\Jobs\CrawlWebsitePagesJob;
use App\Models\CrawlRun;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Services\Crawler\CrawlFrontierBuilder;
use App\Support\Audit\SafeHttpGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrawlSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function allowAllGuard(): void
    {
        $this->app->instance(SafeHttpGuard::class, new class extends SafeHttpGuard
        {
            public function check(string $url): array
            {
                return preg_match('#^https?://#i', trim($url)) ? ['ok' => true] : ['ok' => false, 'reason' => 'bad'];
            }
        });
    }

    public function test_sitemap_delta_seeds_only_new_urls_and_triggers_crawl(): void
    {
        Queue::fake();
        $this->allowAllGuard();
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset><url><loc>https://example.com/old</loc></url><url><loc>https://example.com/new</loc></url></urlset>'),
        ]);

        $user = User::factory()->create();
        $website = Website::factory()->withBothSources()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $website->sitemaps()->create(['path' => 'https://example.com/sitemap.xml', 'source' => 'manual']);
        // /old is already known; /new is not.
        WebsitePage::create(['crawl_site_id' => $website->crawl_site_id, 'url' => 'https://example.com/old', 'url_hash' => WebsitePage::hashUrl('https://example.com/old')]);

        (new CrawlSitemapDeltaJob($website->id))->handle(app(\App\Support\Crawler\SitemapUrlExtractor::class));

        $this->assertDatabaseHas('website_pages', [
            'crawl_site_id' => $website->crawl_site_id,
            'url_hash' => WebsitePage::hashUrl('https://example.com/new'),
            'source_sitemap' => true,
        ]);
        // Exactly the new URL was added (old + new = 2 rows).
        $this->assertSame(2, WebsitePage::where('crawl_site_id', $website->crawl_site_id)->count());
        Queue::assertPushed(CrawlWebsitePagesJob::class);
    }

    public function test_sitemap_delta_noop_when_nothing_new(): void
    {
        Queue::fake();
        $this->allowAllGuard();
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset><url><loc>https://example.com/old</loc></url></urlset>'),
        ]);
        $user = User::factory()->create();
        $website = Website::factory()->withBothSources()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $website->sitemaps()->create(['path' => 'https://example.com/sitemap.xml', 'source' => 'manual']);
        WebsitePage::create(['crawl_site_id' => $website->crawl_site_id, 'url' => 'https://example.com/old', 'url_hash' => WebsitePage::hashUrl('https://example.com/old')]);

        // Reset the fake: withBothSources() itself dispatches ReprocessCompetitiveData
        // on the gsc_google_account_id null->set transition (Website::updated hook) —
        // unrelated to the delta job under test, so don't let it count as "pushed".
        Queue::fake();
        (new CrawlSitemapDeltaJob($website->id))->handle(app(\App\Support\Crawler\SitemapUrlExtractor::class));

        Queue::assertNothingPushed();
    }

    public function test_backfill_only_targets_never_crawled_sites(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $crawled = Website::factory()->create(['user_id' => $user->id, 'domain' => 'crawled.com', 'gsc_site_url' => 'sc-domain:crawled.com']);
        $never = Website::factory()->create(['user_id' => $user->id, 'domain' => 'never.com', 'gsc_site_url' => 'sc-domain:never.com']);
        CrawlRun::create(['crawl_site_id' => $crawled->crawl_site_id, 'trigger' => 'scheduled', 'status' => 'completed', 'started_at' => now()->subDay()]);

        $this->artisan('ebq:crawl-websites --backfill')->assertSuccessful();

        Queue::assertPushed(CrawlWebsitePagesJob::class, 1);
        Queue::assertPushed(CrawlWebsitePagesJob::class, fn ($job) => $job->websiteId === $never->id);
    }

    public function test_orchestrator_creates_run_and_batches_pages(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $website = Website::factory()->withBothSources()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => now()->subDay()->toDateString(), 'query' => 'k',
            'page' => 'https://example.com/p', 'clicks' => 1, 'impressions' => 10, 'position' => 5,
            'country' => 'USA', 'device' => 'DESKTOP', 'ctr' => 0.1,
        ]);

        (new CrawlWebsitePagesJob($website->id, CrawlRun::TRIGGER_MANUAL))->handle(app(CrawlFrontierBuilder::class));

        $this->assertDatabaseHas('crawl_runs', ['crawl_site_id' => $website->crawl_site_id, 'status' => 'running']);
        $this->assertDatabaseHas('website_pages', ['crawl_site_id' => $website->crawl_site_id, 'url_hash' => WebsitePage::hashUrl('https://example.com/p')]);

        // The orchestrator hands off to the multi-pass loop (CrawlPassJob), which is
        // what actually fans the due pages out into a Bus::batch of CrawlPageBatchJob.
        $run = CrawlRun::where('crawl_site_id', $website->crawl_site_id)->first();
        Bus::assertDispatched(CrawlPassJob::class, fn ($job) => $job->crawlRunId === $run->id);
        (new CrawlPassJob($run->id, $website->crawl_site_id))->handle();
        Bus::assertBatched(fn ($batch) => $batch->jobs->first() instanceof CrawlPageBatchJob);
    }
}
