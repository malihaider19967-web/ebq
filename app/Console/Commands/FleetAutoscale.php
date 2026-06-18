<?php

namespace App\Console\Commands;

use App\Jobs\Fleet\BootstrapCrawlWorkerJob;
use App\Jobs\Fleet\ProvisionCrawlWorkerJob;
use App\Jobs\Fleet\RefreshWorkerSnapshotJob;
use App\Models\WorkerNode;
use App\Services\Fleet\HetznerClient;
use App\Services\Fleet\WorkerFleetService;
use App\Support\AutoscalerConfig;
use App\Support\Queues;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

/**
 * The autoscaler control loop (scheduled every 2 min, withoutOverlapping).
 *
 *   desired = clamp(ceil(crawlBacklog / target_backlog_per_box), min, max)
 *
 * ONE box per change, then OBSERVE — never fan out or cascade on a momentary backlog
 * wobble. Scale UP: at most one box, gated by "no node still provisioning" AND a settle
 * window (wait scale_up_cooldown_s since the fleet last changed size, so a freshly-active
 * box can draw down the backlog before we add another). Scale DOWN: at most one box per
 * idle window — the idle clock RE-ARMS after each drain, so a transient dip can't drain the
 * whole fleet (3→2 stops at the max_boxes cap, it won't barrel to 1). Boxes must be past
 * their minimum (hourly-billed) lifetime; the pinned box is never touched. A killed worker's
 * in-flight work is recovered by stop_grace + retry_after + CrawlSupervisor, so drains are safe.
 *
 * `--dry-run` logs the decision without calling Hetzner.
 */
class FleetAutoscale extends Command
{
    protected $signature = 'ebq:fleet-autoscale {--dry-run}';

    protected $description = 'Scale the crawl-worker fleet up/down to match crawl-queue backlog.';

    private const MARKER = 'autoscaler:scale_down_since';

    /** Re-armed whenever the fleet size CHANGES; scale-up waits a cooldown from it to observe. */
    private const SETTLE_MARK = 'autoscaler:fleet_settled_since';

    private const LAST_BILLABLE = 'autoscaler:last_billable';

    /** Give up + replace a box that hasn't finished bootstrapping after this long. */
    private const PROVISION_STUCK_MINUTES = 18;

    /** Cache-key prefix: a re-bootstrap is already queued for this node (avoid piling up). */
    private const REBOOTSTRAP_MARK = 'autoscaler:rebootstrap:';

    public function handle(WorkerFleetService $fleet): int
    {
        if (! AutoscalerConfig::enabled()) {
            return self::SUCCESS; // master kill-switch off
        }
        $dry = (bool) $this->option('dry-run');

        if (! $dry) {
            $fleet->reconcile();
            $this->reapDrained($fleet);
            $this->recoverStuck($fleet);
        }

        $backlog = $this->backlog();
        $billable = $fleet->billableCount();
        $desired = WorkerFleetService::desiredFromBacklog($backlog);
        $ctx = "backlog={$backlog} billable={$billable} desired={$desired}";

        // Whenever the fleet size CHANGES (a box finished provisioning and became billable, or
        // one was reaped), re-arm the settle clock so the next scale-UP waits a full cooldown to
        // SEE the effect before adding another box. "Add one, then observe."
        if (! $dry && $billable !== (int) Cache::get(self::LAST_BILLABLE, $billable)) {
            Cache::put(self::SETTLE_MARK, now()->timestamp, 86400);
        }
        if (! $dry) {
            Cache::put(self::LAST_BILLABLE, $billable, 86400);
        }

        if ($desired > $billable) {
            $this->clearMarker($dry);
            $this->scaleUp($fleet, $dry, $ctx);
        } elseif ($desired < $billable) {
            $this->scaleDown($fleet, $dry, $ctx);
        } else {
            $this->clearMarker($dry);
            $this->say("hold — {$ctx}", $dry);
        }

        return self::SUCCESS;
    }

    private function scaleUp(WorkerFleetService $fleet, bool $dry, string $ctx): void
    {
        if (WorkerNode::where('status', WorkerNode::STATUS_PROVISIONING)->exists()) {
            $this->say("scale-up skip: a node is still provisioning — {$ctx}", $dry);

            return;
        }
        // Observe window: wait a full cooldown since the fleet last changed size, so a freshly
        // active box has had time to draw down the backlog before we decide we need ANOTHER one.
        // Combined with the provisioning gate above → strictly one box added per observe window.
        $settled = (int) Cache::get(self::SETTLE_MARK, 0);
        if ($settled > 0 && now()->timestamp - $settled < AutoscalerConfig::scaleUpCooldownSeconds()) {
            $wait = AutoscalerConfig::scaleUpCooldownSeconds() - (now()->timestamp - $settled);
            $this->say("scale-up skip: observing last fleet change (~{$wait}s left) — {$ctx}", $dry);

            return;
        }
        if (! app(HetznerClient::class)->configured()) {
            $this->say("scale-up skip: HCLOUD_TOKEN not configured — {$ctx}", $dry);

            return;
        }
        // Preflight 1 — the configured snapshot must still EXIST in Hetzner. Without
        // this, a deleted snapshot makes provision() 422 ("image not found") every
        // tick and the autoscaler loops provision→reap a dead node (2026-06-18: the
        // worker snapshot was deleted during unrelated Hetzner cleanup; HEAD still
        // matched so the drift gate below let it through).
        if (! $this->snapshotExists($dry, $ctx)) {
            return;
        }
        // Preflight 2 — don't build a box from a STALE base: if auto-snapshot is on and
        // the snapshot wasn't built from the current git HEAD, kick a background rebuild
        // and DEFER provisioning until it's fresh (the hourly refresh may not have run
        // yet). Skips the tick (retries every 2 min); does NOT block 15 min in one tick.
        if (! $this->snapshotReady($dry, $ctx)) {
            return;
        }
        if ($dry) {
            $this->say("WOULD scale up (+1 box) — {$ctx}", true);

            return;
        }
        // Dispatch to the FLEET queue (ROOT) — do NOT provision+bootstrap here. This
        // command runs via the scheduler as www-data, which cannot read root's
        // /root/.ssh/id_ed25519_worker, so an in-process bootstrap's SSH always times out
        // and the box gets stuck on snapshot code. The root ebq-queue-fleet worker has the
        // key. (Next tick's "a node is still provisioning" gate prevents double-dispatch.)
        ProvisionCrawlWorkerJob::dispatch();
        $this->say("scaled up: dispatched ProvisionCrawlWorkerJob (fleet queue) — {$ctx}", false);
    }

    private function scaleDown(WorkerFleetService $fleet, bool $dry, string $ctx): void
    {
        $since = $this->marker(); // first tick at desired<billable starts the idle clock
        if ($since->diffInSeconds(now()) < AutoscalerConfig::scaleDownIdleSeconds()) {
            $this->say("scale-down pending: idle window not met — {$ctx}", $dry);

            return;
        }
        $node = WorkerNode::drainable()->get()
            ->first(fn (WorkerNode $n) => $n->ageMinutes() * 60 >= AutoscalerConfig::minBoxLifetimeSeconds());
        if (! $node) {
            $this->say("scale-down skip: no drainable box past min lifetime — {$ctx}", $dry);

            return;
        }
        if ($dry) {
            $this->say("WOULD scale down: drain node {$node->id} — {$ctx}", true);

            return;
        }
        $fleet->drain($node);
        // Re-arm the idle clock so we drain only ONE box per idle window: the next drain must
        // wait a fresh scale_down_idle_s of SUSTAINED desired<billable. Without this a momentary
        // backlog dip cascaded into draining multiple boxes in consecutive ticks (3→2→1 instead
        // of stopping at the max_boxes cap). One step, then re-observe.
        Cache::put(self::MARKER, now()->timestamp, 86400);
        $this->say("scaling down: draining node {$node->id} — {$ctx}", false);
    }

    /** Destroy boxes that have been draining past the stop-grace window. */
    private function reapDrained(WorkerFleetService $fleet): void
    {
        $grace = 360 + 90; // stop_grace_period + buffer
        foreach (WorkerNode::where('status', WorkerNode::STATUS_DRAINING)->get() as $node) {
            if ($node->isDrainOverdue($grace)) {
                $fleet->destroy($node);
            }
        }
    }

    /**
     * Self-heal half-provisioned boxes so the fleet recovers WITHOUT manual help —
     * the whole point being an operator can use /admin/fleet and trust it:
     *  - FAILED unpinned boxes (bad image, vanished server) → destroy; the scale
     *    loop provisions a fresh one next tick.
     *  - boxes stuck at 'provisioning' (bootstrap aborted — e.g. SSH came up after
     *    the wait window) → RE-RUN bootstrap (it's idempotent), which rsyncs current
     *    code + force-recreates the Horizon container. So a box can never sit running
     *    the snapshot's OLD code, nor block scale-up forever. If it's been stuck too
     *    long it's genuinely broken → destroy and let the loop replace it.
     * bootstrap() now also stops the snapshot's stale containers on connect, so even
     * mid-recovery a box isn't pulling jobs with old code.
     */
    private function recoverStuck(WorkerFleetService $fleet): void
    {
        foreach (WorkerNode::where('is_pinned', false)->where('status', WorkerNode::STATUS_FAILED)->get() as $node) {
            $this->say("reaping failed node {$node->id} ({$node->last_error})", false);
            $fleet->destroy($node);
        }

        foreach (WorkerNode::where('is_pinned', false)->where('status', WorkerNode::STATUS_PROVISIONING)->get() as $node) {
            if ($node->ageMinutes() >= self::PROVISION_STUCK_MINUTES) {
                $this->say("provisioning stuck {$node->ageMinutes()}m — destroying node {$node->id} ({$node->last_error})", false);
                $fleet->destroy($node); // destroy is a Hetzner API call → fine as www-data

                continue;
            }
            // Re-bootstrap on the ROOT fleet queue (this command is www-data; bootstrap
            // SSHes with root's key). Cache marker so we don't pile up bootstrap jobs while
            // one is already running (~15 min) on the single-process fleet queue.
            if (Cache::has(self::REBOOTSTRAP_MARK.$node->id)) {
                continue;
            }
            Cache::put(self::REBOOTSTRAP_MARK.$node->id, 1, 720);
            $this->say("re-bootstrapping stuck node {$node->id} (age {$node->ageMinutes()}m) via fleet queue", false);
            BootstrapCrawlWorkerJob::dispatch($node->id);
        }
    }

    /**
     * Safe to provision from the current snapshot? When auto-snapshot is on and the
     * snapshot's git HEAD != deployed HEAD, kick a (self-locked, background) rebuild and
     * return false so scale-up DEFERS to a later tick — never building a box from a stale
     * base. No-op (true) when auto-snapshot is off (operator manages the image manually).
     */
    /**
     * Verify the configured worker snapshot still exists in Hetzner before we try to
     * build a box from it. Confirmed-missing → rebuild (if auto_snapshot) or skip with
     * an actionable error; can't-verify (transient API error) → just hold this tick and
     * retry, without triggering a rebuild on a network blip.
     */
    private function snapshotExists(bool $dry, string $ctx): bool
    {
        $id = AutoscalerConfig::snapshotId();
        if ($id === null) {
            $this->say("scale-up skip: no worker snapshot configured — build one (ebq:refresh-worker-snapshot) and set snapshot_id at /admin/fleet — {$ctx}", $dry);

            return false;
        }

        $exists = app(HetznerClient::class)->imageExists((int) $id);
        if ($exists === true) {
            return true;
        }
        if ($exists === null) {
            $this->say("scale-up skip: could not verify worker snapshot {$id} exists (Hetzner API) — {$ctx}", $dry);

            return false;
        }

        // Confirmed gone (404). Self-heal via rebuild if auto-snapshot is on.
        if (AutoscalerConfig::autoSnapshot()) {
            if (! $dry && ! Cache::has(RefreshWorkerSnapshotJob::IN_PROGRESS)) {
                RefreshWorkerSnapshotJob::dispatch();
            }
            $this->say("scale-up deferred: worker snapshot {$id} missing in Hetzner — rebuilding — {$ctx}", $dry);
        } else {
            $this->say("scale-up skip: worker snapshot {$id} not found in Hetzner and auto_snapshot is OFF — rebuild it (ebq:refresh-worker-snapshot) and set snapshot_id at /admin/fleet — {$ctx}", $dry);
        }

        return false;
    }

    private function snapshotReady(bool $dry, string $ctx): bool
    {
        if (! AutoscalerConfig::autoSnapshot()) {
            return true;
        }
        $head = trim((string) Process::run('git -C /var/www/ebq rev-parse HEAD')->output());
        if ($head === '' || $head === AutoscalerConfig::snapshotHead()) {
            return true;
        }
        if (! $dry && ! Cache::has(RefreshWorkerSnapshotJob::IN_PROGRESS)) {
            // Build runs on the ROOT fleet queue (it SSHes; this command is www-data and
            // can't read the key). Self-locked, so a duplicate dispatch is harmless.
            RefreshWorkerSnapshotJob::dispatch();
        }
        $this->say("scale-up deferred: worker snapshot rebuilding for HEAD {$head} — {$ctx}", $dry);

        return false;
    }

    private function backlog(): int
    {
        try {
            return (int) Queue::connection('redis')->size(Queues::CRAWL);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function marker(): Carbon
    {
        $ts = Cache::get(self::MARKER);
        if (! $ts) {
            $ts = now()->timestamp;
            Cache::put(self::MARKER, $ts, 86400);
        }

        return Carbon::createFromTimestamp($ts);
    }

    private function clearMarker(bool $dry): void
    {
        if (! $dry) {
            Cache::forget(self::MARKER);
        }
    }

    private function say(string $msg, bool $dry): void
    {
        $this->info(($dry ? '[dry] ' : '').$msg);
        if (! $dry) {
            Log::info('FleetAutoscale: '.$msg);
        }
    }
}
