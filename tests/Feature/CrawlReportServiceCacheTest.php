<?php

namespace Tests\Feature;

use App\Models\CrawlFinding;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Services\Crawler\CrawlReportService;
use App\Services\ReportCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlReportServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    private function makePage(Website $website, string $url): WebsitePage
    {
        return WebsitePage::create([
            'crawl_site_id' => $website->crawl_site_id, 'url' => $url, 'url_hash' => WebsitePage::hashUrl($url),
            'http_status' => 200, 'is_indexable' => true, 'last_crawled_at' => now(),
        ]);
    }

    private function makeFinding(Website $website, WebsitePage $page, string $type): CrawlFinding
    {
        return CrawlFinding::create([
            'crawl_site_id' => $website->crawl_site_id, 'page_id' => $page->id,
            'category' => CrawlFinding::CATEGORY_ONPAGE, 'type' => $type, 'severity' => 'medium', 'impact' => 0,
            'affected_url' => $page->url, 'affected_url_hash' => $page->url_hash,
            'detail' => [], 'status' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
    }

    public function test_audit_results_are_cached_until_the_crawl_version_is_bumped(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $page = $this->makePage($website, 'https://example.com/one');
        $this->makeFinding($website, $page, 'thin_content');

        $service = app(CrawlReportService::class);

        $first = $service->typeBreakdown('onpage', $website->id);
        $this->assertSame(1, $first[0]['count']);

        // A new finding lands without a cache bump — the slow query must NOT
        // re-run, so the stale (pre-finding) count is what comes back.
        $page2 = $this->makePage($website, 'https://example.com/two');
        $this->makeFinding($website, $page2, 'thin_content');

        $stillCached = $service->typeBreakdown('onpage', $website->id);
        $this->assertSame(1, $stillCached[0]['count'], 'expected the cached (stale) count, not a fresh query');

        // Simulates AnalyzeSiteJob::flushSubscribers() at the end of a crawl run.
        ReportCache::flushWebsite($website->id);

        $fresh = $service->typeBreakdown('onpage', $website->id);
        $this->assertSame(2, $fresh[0]['count'], 'expected the fresh count after the version bump');
    }
}
