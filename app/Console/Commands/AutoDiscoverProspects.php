<?php

namespace App\Console\Commands;

use App\Models\Website;
use App\Services\BacklinkProspectingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Nightly auto-discovery of backlink prospects for every connected
 * website.
 *
 * For each website:
 *   1. Pulls competitor domains from `PageAuditReport.result.benchmark.competitors`
 *      over the last 30 days (only present on non-lite audits — i.e. HQ
 *      → Page Audits triggered runs).
 *   2. Calls `BacklinkProspectingService::prospect()` against that list.
 *      The freshness gate suppresses any KE call inside its TTL window,
 *      so re-runs are KE-credit-safe.
 *   3. New prospects land in `outreach_prospects` with `status='new'`;
 *      existing rows get `linked_to_competitors` merged + `last_seen_at`
 *      bumped without losing their workflow state.
 *
 * Result: when a user opens HQ → Prospects in the morning, their kanban
 * already reflects yesterday's audits. No "open tab → see nothing →
 * paste competitors → wait" friction.
 */
class AutoDiscoverProspects extends Command
{
    protected $signature = 'ebq:auto-discover-prospects {--days=30 : Look-back window for audit competitors}';

    protected $description = 'Auto-discover backlink prospects from each website\'s recent page audits';

    public function handle(BacklinkProspectingService $service): int
    {
        $days = (int) $this->option('days');
        $totalSites = 0;
        $totalNew = 0;
        $totalDiscovered = 0;
        $errors = 0;

        Website::query()->select(['id', 'domain', 'user_id'])->chunkById(50, function ($websites) use ($service, $days, &$totalSites, &$totalNew, &$totalDiscovered, &$errors): void {
            foreach ($websites as $website) {
                $totalSites++;
                try {
                    $result = $service->autoDiscoverFromAudits($website, $days);
                    $totalDiscovered += (int) ($result['discovered_competitors'] ?? 0);
                    $totalNew += (int) ($result['new_in_run'] ?? 0);
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('AutoDiscoverProspects: per-website failure', [
                        'website_id' => $website->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info(sprintf(
            'Auto-discovery complete: %d sites scanned, %d competitors total, %d new prospects, %d errors.',
            $totalSites, $totalDiscovered, $totalNew, $errors,
        ));

        return self::SUCCESS;
    }
}
