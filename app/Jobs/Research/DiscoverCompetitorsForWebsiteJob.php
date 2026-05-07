<?php

namespace App\Jobs\Research;

use App\Models\Website;
use App\Services\Research\CompetitorDiscoveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * For a website with GSC data, discover competitors via SERP for top
 * queries and enqueue them as `research_targets`. Idempotent — running
 * twice updates priority on existing rows but never duplicates.
 */
class DiscoverCompetitorsForWebsiteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public int $websiteId, public int $maxKeywords = 5) {}

    public function handle(CompetitorDiscoveryService $service): void
    {
        if (\App\Support\ResearchEngineSettings::enginePaused()
            || \App\Support\ResearchEngineSettings::autoDiscoveryDisabled()) {
            \Illuminate\Support\Facades\Log::info('DiscoverCompetitorsForWebsiteJob: skipped — engine paused or auto-discovery disabled', [
                'website_id' => $this->websiteId,
            ]);
            return;
        }

        $website = Website::query()->find($this->websiteId);
        if ($website === null) {
            return;
        }

        try {
            $found = $service->discoverForWebsite($website, $this->maxKeywords);
            Log::info('DiscoverCompetitorsForWebsiteJob: discovered '.$found->count().' competitor(s)', [
                'website_id' => $this->websiteId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('DiscoverCompetitorsForWebsiteJob failed: '.$e->getMessage(), [
                'website_id' => $this->websiteId,
            ]);
            throw $e;
        }
    }
}
