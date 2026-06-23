<?php

namespace Tests\Feature;

use App\Jobs\CrawlWebsitePagesJob;
use App\Models\CrawlRun;
use App\Models\Plan;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Services\Crawler\CrawlSiteBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AccountPooledCrawlCapTest extends TestCase
{
    use RefreshDatabase;

    private function siteWithCrawledPages(User $owner, string $domain, int $crawledPages): Website
    {
        $website = Website::factory()->create(['user_id' => $owner->id, 'domain' => $domain]);
        $cs = $website->crawl_site_id;
        CrawlRun::create(['crawl_site_id' => $cs, 'trigger' => 'manual', 'status' => 'completed', 'started_at' => now(), 'finished_at' => now()]);

        for ($i = 1; $i <= $crawledPages; $i++) {
            WebsitePage::create([
                'crawl_site_id' => $cs, 'url' => "https://{$domain}/{$i}", 'url_hash' => WebsitePage::hashUrl("https://{$domain}/{$i}"),
                'http_status' => 200, 'is_indexable' => true, 'value_rank' => $i, 'last_crawled_at' => now(),
            ]);
        }

        return $website;
    }

    public function test_single_site_account_gets_hard_cap_when_quota_null(): void
    {
        config(['app.free' => false, 'crawler.max_pages_per_site' => 20000]);
        $website = Website::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'noplan.com']);

        $this->assertSame(20000, $website->crawlPageCap());
    }

    public function test_single_site_account_capped_at_min_of_hardcap_and_quota(): void
    {
        config(['app.free' => false, 'crawler.max_pages_per_site' => 20000]);
        Plan::create(['slug' => 'free', 'name' => 'Free', 'max_crawl_pages' => 300, 'is_active' => true]);
        $website = Website::factory()->create(['user_id' => User::factory()->create(['current_plan_slug' => 'free'])->id, 'domain' => 'freeuser.com']);

        $this->assertSame(300, $website->crawlPageCap());
    }

    public function test_three_sites_full_pool_each_capped_at_hard_cap(): void
    {
        config(['app.free' => false, 'crawler.max_pages_per_site' => 5]);
        Plan::create(['slug' => 'agency', 'name' => 'Agency', 'max_crawl_pages' => 15, 'is_active' => true]);
        $owner = User::factory()->create(['current_plan_slug' => 'agency']);

        $a = $this->siteWithCrawledPages($owner, 'site-a.com', 5);
        $b = $this->siteWithCrawledPages($owner, 'site-b.com', 5);
        $c = $this->siteWithCrawledPages($owner, 'site-c.com', 5);

        // Pool = 15, hard cap = 5, 3 sites each at/above the hard cap → each gets exactly 5.
        $this->assertSame(5, $a->crawlPageCap());
        $this->assertSame(5, $b->crawlPageCap());
        $this->assertSame(5, $c->crawlPageCap());
    }

    public function test_two_sites_leave_pool_unused_not_redistributed(): void
    {
        config(['app.free' => false, 'crawler.max_pages_per_site' => 5]);
        Plan::create(['slug' => 'agency', 'name' => 'Agency', 'max_crawl_pages' => 15, 'is_active' => true]);
        $owner = User::factory()->create(['current_plan_slug' => 'agency']);

        $a = $this->siteWithCrawledPages($owner, 'site-a.com', 5);
        $b = $this->siteWithCrawledPages($owner, 'site-b.com', 5);

        // Pool = 15, only 2 sites consuming 5 each = 10 used, 5 unused — neither site's
        // cap relaxes past the hard cap to absorb the unused 5.
        $this->assertSame(5, $a->crawlPageCap());
        $this->assertSame(5, $b->crawlPageCap());
    }

    public function test_new_site_after_pool_exhausted_gets_floor_not_blocked(): void
    {
        Bus::fake();
        config(['app.free' => false, 'crawler.max_pages_per_site' => 5]);
        Plan::create(['slug' => 'agency', 'name' => 'Agency', 'max_crawl_pages' => 10, 'is_active' => true]);
        $owner = User::factory()->create(['current_plan_slug' => 'agency']);

        $this->siteWithCrawledPages($owner, 'site-a.com', 5);
        $this->siteWithCrawledPages($owner, 'site-b.com', 5);

        // Pool of 10 fully consumed by the first two sites — a third site still
        // gets subscribed/crawled, just floored at 1 page, not blocked.
        $c = Website::factory()->create(['user_id' => $owner->id, 'domain' => 'site-c.com']);
        $this->assertSame(1, $c->crawlPageCap());

        app(CrawlSiteBootstrapper::class)->subscribeWebsite($c);
        Bus::assertChained([\App\Jobs\SyncSitemaps::class, CrawlWebsitePagesJob::class]);
    }

    public function test_unlimited_quota_account_still_hard_capped_per_site(): void
    {
        config(['app.free' => false, 'crawler.max_pages_per_site' => 5]);
        Plan::create(['slug' => 'enterprise', 'name' => 'Enterprise', 'max_crawl_pages' => null, 'is_active' => true]);
        $owner = User::factory()->create(['current_plan_slug' => 'enterprise']);

        $a = $this->siteWithCrawledPages($owner, 'site-a.com', 50);
        $b = Website::factory()->create(['user_id' => $owner->id, 'domain' => 'site-b.com']);

        $this->assertSame(5, $a->crawlPageCap());
        $this->assertSame(5, $b->crawlPageCap());
    }
}
