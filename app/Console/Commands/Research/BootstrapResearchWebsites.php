<?php

namespace App\Console\Commands\Research;

use App\Jobs\Research\DiscoverCompetitorsForWebsiteJob;
use App\Models\Research\ResearchTarget;
use App\Models\SearchConsoleData;
use App\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily safety net for the website-onboarding bootstrap. Catches any
 * Website that has GSC data but no auto-discovered competitors yet —
 * happens when the immediate post-create dispatch ran before GSC sync
 * finished, when a website was created before the research feature
 * shipped, or when CompetitorDiscoveryService failed transiently.
 */
class BootstrapResearchWebsites extends Command
{
    protected $signature = 'ebq:research-bootstrap-websites
                            {--dry-run : Print the list without dispatching}';

    protected $description = 'Dispatch DiscoverCompetitorsForWebsiteJob for websites that have GSC data but no SERP-derived competitor research yet.';

    public function handle(): int
    {
        if (\App\Support\ResearchEngineSettings::enginePaused()) {
            $this->info('Research engine is paused — bootstrap skipped.');

            return self::SUCCESS;
        }
        if (\App\Support\ResearchEngineSettings::autoDiscoveryDisabled()) {
            $this->info('Auto Serper discovery is disabled — bootstrap skipped (queued targets continue normally).');

            return self::SUCCESS;
        }

        $websites = Website::query()
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('search_console_data')
                    ->whereColumn('search_console_data.website_id', 'websites.id');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('research_targets')
                    ->whereColumn('research_targets.attached_website_id', 'websites.id')
                    ->where('research_targets.source', ResearchTarget::SOURCE_SERP_COMPETITOR);
            })
            ->get(['id', 'domain']);

        $this->line("BootstrapResearchWebsites: {$websites->count()} candidate(s).");

        $dryRun = (bool) $this->option('dry-run');
        foreach ($websites as $website) {
            if ($dryRun) {
                $this->line('  · would dispatch for website #'.$website->id.' ('.$website->domain.')');
                continue;
            }
            DiscoverCompetitorsForWebsiteJob::dispatch($website->id);
        }

        return self::SUCCESS;
    }
}
