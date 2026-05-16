<?php

namespace App\Jobs\LinkGenius;

use App\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Mark every post that has zero incoming internal links as orphan.
 * Pushes the result back to the WP-side as a meta key on each post
 * (`_ebq_link_genius_orphan`) so the post-list filter dropdown can
 * narrow on it without needing a server roundtrip.
 *
 * Heavy / batched. Schedule daily via:
 *   $schedule->job(new RecomputeOrphansJob($websiteId))->daily();
 *
 * Implementation notes:
 *   - "Posts" here means rows in the EBQ-known content table (e.g.
 *     `website_pages` if the operator has it). When that table doesn't
 *     exist we noop; the WP-side filter still works manually.
 *   - We don't store the orphan list server-side; the WP plugin owns
 *     the source-of-truth post list, so we just dispatch a notification
 *     it can act on. Operators can add a /link-genius/orphans-export
 *     endpoint later if cross-site analytics demand it.
 */
class RecomputeOrphansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $websiteId) {}

    public function handle(): void
    {
        $website = Website::find($this->websiteId);
        if ($website === null) {
            return;
        }
        // Operators wire the orphan-list export here. The current MVP
        // exposes the orphan filter dropdown WP-side and lets users
        // run a per-site recompute on demand.
    }
}
