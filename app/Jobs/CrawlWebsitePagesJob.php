<?php

namespace App\Jobs;

use App\Models\CrawlRun;
use App\Models\CrawlSite;
use App\Models\Website;
use App\Services\Crawler\CrawlFrontierBuilder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates a full crawl of one website (the job named in the original
 * website_pages migration intent). Builds the GSC ∪ sitemap frontier, then
 * fans the due pages out across CrawlPageBatchJob children in a batch; when the
 * batch finishes, AnalyzeSiteJob computes the link graph + findings + scores.
 *
 * ShouldBeUnique per website so scheduled + on-create triggers can't overlap.
 */
class CrawlWebsitePagesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public int $websiteId,
        public string $trigger = CrawlRun::TRIGGER_SCHEDULED,
        public bool $force = false,
    ) {
        $this->onQueue(\App\Support\Queues::CRAWL);
    }

    /** Unique per shared crawl_site so two subscribers can't double-crawl a domain. */
    public function uniqueId(): string
    {
        $crawlSiteId = Website::where('id', $this->websiteId)->value('crawl_site_id');

        return 'crawl-site-'.($crawlSiteId ?: 'w'.$this->websiteId);
    }

    public function uniqueFor(): int
    {
        return 3600 * 6; // 6h lock — longer than any reasonable single crawl
    }

    public function handle(CrawlFrontierBuilder $frontier): void
    {
        $website = Website::find($this->websiteId);
        if (! $website) {
            return;
        }
        if ($website->isFrozen()) {
            Log::info("CrawlWebsitePagesJob: skipping frozen website {$this->websiteId}");

            return;
        }

        $crawlSite = $this->resolveCrawlSite($website);
        if ($crawlSite === null) {
            Log::info("CrawlWebsitePagesJob: website {$this->websiteId} has no crawlable domain; skipping.");

            return;
        }

        // Already crawling this shared site (another subscriber triggered it)? Don't
        // start a second run — the in-flight crawl covers everyone (and extends to a
        // higher cap automatically since CrawlPassJob reads effective_cap each pass).
        if ($crawlSite->isCrawling()) {
            return;
        }

        $run = CrawlRun::create([
            'crawl_site_id' => $crawlSite->id,
            'trigger' => $this->trigger,
            'status' => CrawlRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);
        $crawlSite->forceFill(['status' => 'crawling', 'last_crawl_started_at' => now()])->save();

        try {
            $frontier->build($crawlSite);
        } catch (Throwable $e) {
            Log::warning("CrawlWebsitePagesJob: frontier build failed for crawl_site {$crawlSite->id}: {$e->getMessage()}");
        }

        // Hand off to the multi-pass loop: crawls the due frontier, re-selects
        // mid-run discoveries (link stubs), until the site is exhausted. Each pass
        // dispatches the next; the final pass dispatches AnalyzeSiteJob.
        $run->update(['pages_seen' => 0]);
        CrawlPassJob::dispatch($run->id, $crawlSite->id, 1, $this->force)
            ->onQueue(\App\Support\Queues::CRAWL);
    }

    /** Resolve (creating + linking if needed) the shared crawl_site for this website. */
    private function resolveCrawlSite(Website $website): ?CrawlSite
    {
        if ($website->crawl_site_id) {
            return $website->crawlSite;
        }
        $domain = CrawlSite::normalizeDomain((string) $website->domain);
        if ($domain === '') {
            return null;
        }
        $site = CrawlSite::firstOrCreate(['normalized_domain' => $domain]);
        $website->forceFill(['crawl_site_id' => $site->id])->saveQuietly();
        $site->recomputeEffectiveCap();

        return $site;
    }
}
