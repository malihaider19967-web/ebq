<?php

namespace Tests\Feature;

use App\Models\CrawlFinding;
use App\Models\CrawlRun;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteInternalLink;
use App\Models\WebsitePage;
use App\Services\Crawler\SiteIssueDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteIssueDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_orphan_findings_skip_query_string_urls(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $cs = $website->crawl_site_id;
        $run = CrawlRun::create(['crawl_site_id' => $cs, 'trigger' => 'manual', 'status' => 'running', 'started_at' => now()]);

        // Clean-path page with no inbound links -> a real orphan.
        WebsitePage::create(['crawl_site_id' => $cs, 'url' => 'https://example.com/clean', 'url_hash' => WebsitePage::hashUrl('https://example.com/clean'), 'http_status' => 200, 'is_indexable' => true, 'inbound_link_count' => 0, 'last_crawled_at' => now()]);
        // Parameter URL with no inbound links -> expected, NOT flagged.
        WebsitePage::create(['crawl_site_id' => $cs, 'url' => 'https://example.com/gen?name=max', 'url_hash' => WebsitePage::hashUrl('https://example.com/gen?name=max'), 'http_status' => 200, 'is_indexable' => true, 'inbound_link_count' => 0, 'last_crawled_at' => now()]);

        app(SiteIssueDetector::class)->detect($website->crawlSite, $run);

        $this->assertDatabaseHas('crawl_findings', [
            'crawl_site_id' => $cs, 'type' => 'orphan_page',
            'affected_url_hash' => CrawlFinding::hashUrl('https://example.com/clean'),
        ]);
        $this->assertDatabaseMissing('crawl_findings', [
            'crawl_site_id' => $cs, 'type' => 'orphan_page',
            'affected_url_hash' => CrawlFinding::hashUrl('https://example.com/gen?name=max'),
        ]);
    }

    public function test_broken_page_finding_carries_discovery_provenance(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $cs = $website->crawl_site_id;
        $run = CrawlRun::create(['crawl_site_id' => $cs, 'trigger' => 'manual', 'status' => 'running', 'started_at' => now()]);

        $home = WebsitePage::create(['crawl_site_id' => $cs, 'url' => 'https://example.com/', 'url_hash' => WebsitePage::hashUrl('https://example.com/'), 'http_status' => 200, 'is_indexable' => true, 'internal_link_count' => 1, 'last_crawled_at' => now()]);
        // A 404 that is both listed in the sitemap AND linked from the homepage.
        $dead = WebsitePage::create(['crawl_site_id' => $cs, 'url' => 'https://example.com/dead', 'url_hash' => WebsitePage::hashUrl('https://example.com/dead'), 'http_status' => 404, 'is_indexable' => false, 'source_sitemap' => true, 'discovered_at' => now()->subDay(), 'last_crawled_at' => now()]);
        WebsiteInternalLink::create(['crawl_site_id' => $cs, 'from_page_id' => $home->id, 'to_page_id' => $dead->id, 'anchor_text' => 'Broken link', 'status' => 'discovered']);

        app(SiteIssueDetector::class)->detect($website->crawlSite, $run);

        $finding = CrawlFinding::where('crawl_site_id', $cs)
            ->where('type', 'broken_page')
            ->where('affected_url_hash', CrawlFinding::hashUrl('https://example.com/dead'))
            ->first();

        $this->assertNotNull($finding, 'expected a broken_page finding for the 404');
        $detail = $finding->detail;
        $this->assertSame(404, $detail['http_status']);
        $this->assertSame(1, $detail['inbound_internal']);
        $this->assertTrue($detail['source_sitemap']);
        $this->assertSame('https://example.com/', $detail['referrers'][0]['url']);
        $this->assertSame('Broken link', $detail['referrers'][0]['anchor']);
    }
}
