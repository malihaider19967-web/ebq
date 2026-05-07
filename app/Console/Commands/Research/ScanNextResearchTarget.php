<?php

namespace App\Console\Commands\Research;

use App\Jobs\Research\RunCompetitorScanJob;
use App\Models\Research\CompetitorScan;
use App\Models\Research\ResearchTarget;
use Illuminate\Console\Command;

/**
 * The continuous-research scheduler tick. Picks the highest-priority
 * queued research_target whose next_scan_at has passed (or is null),
 * creates a competitor_scans row for it, dispatches the scrape, and
 * marks the target row 'scanning'.
 *
 * Throttle: by default scans one target per invocation. Schedule fires
 * every 15 minutes, so this self-paces to ~96 scans/day max — plenty
 * for a single-tenant deploy and keeps Serper/KE costs predictable.
 */
class ScanNextResearchTarget extends Command
{
    protected $signature = 'ebq:research-scan-next
                            {--max=1 : Max number of targets to dispatch this tick}
                            {--dry-run : Print picks without dispatching}';

    protected $description = 'Pick the next queued research_targets row(s) and dispatch a competitor scrape.';

    public function handle(): int
    {
        if (\App\Support\ResearchEngineSettings::enginePaused()) {
            $this->info('Research engine is paused via /admin/research/settings — no targets dispatched.');

            return self::SUCCESS;
        }

        $max = max(1, (int) $this->option('max'));
        $dryRun = (bool) $this->option('dry-run');

        $candidates = ResearchTarget::query()
            ->readyToScan()
            ->limit($max)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No queued research_targets ready to scan.');

            return self::SUCCESS;
        }

        foreach ($candidates as $target) {
            // Refuse to start a second active scan for this domain — the
            // scheduler can race with admin-triggered scans.
            $hasActive = CompetitorScan::query()
                ->where('seed_domain', $target->domain)
                ->active()
                ->exists();
            if ($hasActive) {
                $this->line("  · skipping {$target->domain} — already has an active scan");
                continue;
            }

            $rootUrl = $target->root_url ?: 'https://'.$target->domain.'/';

            if ($dryRun) {
                $this->line("  · would scan {$target->domain} (priority {$target->priority}, source {$target->source})");
                continue;
            }

            $scan = CompetitorScan::create([
                'website_id' => $target->attached_website_id,
                'triggered_by_user_id' => null,
                'seed_domain' => $target->domain,
                'seed_url' => $rootUrl,
                'seed_keywords' => $target->seed_keywords ?? [],
                'caps' => [
                    'max_total_pages' => 250,
                    'max_pages_per_external_domain' => 5,
                    'max_depth' => 4,
                ],
                'status' => CompetitorScan::STATUS_QUEUED,
            ]);

            $target->forceFill([
                'status' => ResearchTarget::STATUS_SCANNING,
            ])->save();

            RunCompetitorScanJob::dispatch($scan->id);

            $this->info("Dispatched scan #{$scan->id} for {$target->domain}");
        }

        return self::SUCCESS;
    }
}
