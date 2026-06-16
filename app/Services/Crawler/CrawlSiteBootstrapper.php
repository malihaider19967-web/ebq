<?php

namespace App\Services\Crawler;

use App\Jobs\CrawlWebsitePagesJob;
use App\Jobs\SyncSitemaps;
use App\Models\CrawlRun;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Services\ClientActivityLogger;
use Illuminate\Support\Facades\Bus;

/**
 * Single entry point for both add-flows (onboarding + WebsitesList): a website
 * SUBSCRIBES to its shared crawl_site (the model hook already created/linked it),
 * gets "charged" its crawl-page allocation (logged usage — there is no enforced
 * budget yet), and triggers a crawl only when one is actually needed:
 *  - no completed crawl exists for the domain yet, or
 *  - this user's cap is larger than what's already been crawled (crawl deeper).
 * When a fresh shared crawl already covers this user's cap, nothing is crawled —
 * the user instantly reads the existing shared data (capped to their plan).
 */
class CrawlSiteBootstrapper
{
    public function __construct(private readonly ClientActivityLogger $logger) {}

    public function subscribeWebsite(Website $website): void
    {
        $crawlSite = $website->crawlSite;
        if (! $crawlSite) {
            return; // domain-only placeholder with no real domain yet
        }

        $cap = $website->crawlPageCap();
        $crawledPages = WebsitePage::where('crawl_site_id', $crawlSite->id)
            ->whereNotNull('last_crawled_at')->count();

        // Charge: this user consumes up to their cap of the shared crawl (the page
        // count already available, bounded by their plan). Visibility/analytics only.
        $this->logger->log(
            'crawl.subscribed',
            userId: $website->user_id,
            websiteId: $website->id,
            provider: 'crawl_reuse',
            meta: ['crawl_site_id' => $crawlSite->id, 'cap' => $cap, 'crawled_pages' => $crawledPages],
            unitsConsumed: min($crawledPages, $cap),
        );

        $hasCompleted = CrawlRun::where('crawl_site_id', $crawlSite->id)
            ->where('status', CrawlRun::STATUS_COMPLETED)->exists();
        $needsCrawl = ! $hasCompleted || $crawledPages < $cap;

        if ($needsCrawl && ! $crawlSite->isCrawling()) {
            // Sync this subscriber's sitemaps into the union, then crawl. The frontier
            // always seeds the homepage, so even a domain-only site gets crawled.
            Bus::chain([
                new SyncSitemaps($website->id),
                new CrawlWebsitePagesJob($website->id, CrawlRun::TRIGGER_ON_CREATE),
            ])->dispatch();
        }
        // else: a fresh shared crawl already covers this user's cap → instant reuse.
    }
}
