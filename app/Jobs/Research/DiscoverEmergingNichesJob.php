<?php

namespace App\Jobs\Research;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Phase-2 stub. Phase-3 implementation will:
 *
 *   1. Pull keywords with no good niche match (max relevance < 0.2).
 *   2. Cluster via the existing ClusteringService.
 *   3. If a cluster persists across 4 weekly runs and exceeds size N,
 *      create an `is_dynamic=true` Niche row with parent_id picked by
 *      LLM and `is_approved=false` for admin review.
 *
 * Wired into the schedule today so the cadence stays visible; the body
 * is intentionally a no-op until the persistence + admin-review surface
 * land in Phase 3.
 */
class DiscoverEmergingNichesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 1;

    public function handle(): void
    {
        Log::info('DiscoverEmergingNichesJob: stub (Phase-2). No-op until Phase-3 persistence ships.');
    }
}
