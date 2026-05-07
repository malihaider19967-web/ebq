<?php

namespace App\Jobs\Research;

use App\Models\Research\CompetitorOutlink;
use App\Models\Research\CompetitorScan;
use App\Models\Research\ResearchTarget;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Post-scan: every external `to_domain` we saw outlinks to becomes a
 * low-priority `research_targets` row. The continuous scheduler will
 * pick them up later. New domains stay queued; existing rows have
 * their priority bumped if outlink count is high.
 *
 * Cap on enqueued rows per scan so a 30k-outlink crawl doesn't fan
 * out into 30k targets in one go.
 */
class AutoEnqueueOutlinksJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public int $scanId, public int $maxNewTargets = 200) {}

    public function handle(): void
    {
        if (\App\Support\ResearchEngineSettings::enginePaused()) {
            return;
        }

        $scan = CompetitorScan::query()->find($this->scanId);
        if ($scan === null || $scan->status !== CompetitorScan::STATUS_DONE) {
            return;
        }

        // Most-linked external domains first — those are the highest
        // signal candidates.
        $domains = CompetitorOutlink::query()
            ->where('competitor_scan_id', $scan->id)
            ->where('is_external', true)
            ->where('to_domain', '!=', $scan->seed_domain)
            ->select('to_domain', DB::raw('COUNT(*) as link_count'))
            ->groupBy('to_domain')
            ->orderByDesc('link_count')
            ->limit($this->maxNewTargets)
            ->get();

        $enqueued = 0;

        foreach ($domains as $row) {
            $domain = mb_strtolower(trim((string) $row->to_domain));
            if ($domain === '' || $domain === $scan->seed_domain) {
                continue;
            }

            $existing = ResearchTarget::query()->where('domain', $domain)->first();
            if ($existing !== null) {
                continue; // never demote priority or change source on existing
            }

            ResearchTarget::create([
                'domain' => $domain,
                'root_url' => 'https://'.$domain.'/',
                'source' => ResearchTarget::SOURCE_OUTLINK,
                'priority' => ResearchTarget::PRIORITY_OUTLINK,
                'status' => ResearchTarget::STATUS_QUEUED,
                'notes' => "Auto-enqueued from scan #{$scan->id} ({$scan->seed_domain}) — {$row->link_count} link(s).",
            ]);
            $enqueued++;
        }

        Log::info("AutoEnqueueOutlinksJob: enqueued {$enqueued} new target(s) from scan #{$scan->id}");
    }
}
