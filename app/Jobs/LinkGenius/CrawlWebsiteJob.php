<?php

namespace App\Jobs\LinkGenius;

use App\Models\Website;
use App\Services\LinkGenius\CrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recheck a batch of Link Genius links for the given website. Long
 * sites get chunked across multiple invocations because each batch
 * caps at `CrawlerService::$batchSize` to keep the per-job runtime
 * bounded.
 *
 * Schedule via:
 *   $schedule->job(new CrawlWebsiteJob($websiteId))->hourly();
 */
class CrawlWebsiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $websiteId) {}

    public function handle(CrawlerService $crawler): void
    {
        $website = Website::find($this->websiteId);
        if ($website === null) {
            return;
        }
        $crawler->recheckBatch($website);
    }
}
