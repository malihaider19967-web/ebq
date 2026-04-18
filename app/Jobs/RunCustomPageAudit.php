<?php

namespace App\Jobs;

use App\Models\CustomPageAudit;
use App\Services\PageAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs a CustomPageAudit in the background.
 *
 * Why the constraints:
 * - tries=1        : Serper/DFS calls cost money. Never auto-retry without a human.
 * - timeout=300    : Generous ceiling; the service usually finishes in <30s.
 * - uniqueFor=1800 : If someone double-clicks or the UI dispatches twice, the second
 *                    dispatch is silently dropped for 30 minutes — same audit_id only runs once.
 */
class RunCustomPageAudit implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 1800;

    public function __construct(public readonly int $auditId) {}

    public function uniqueId(): string
    {
        return 'custom-page-audit:'.$this->auditId;
    }

    public function handle(PageAuditService $service): void
    {
        $audit = CustomPageAudit::query()->find($this->auditId);
        if (! $audit instanceof CustomPageAudit) {
            return; // row was deleted before we got to it — nothing to do.
        }

        // Another worker already picked this up, or a human retried in between.
        if ($audit->status !== CustomPageAudit::STATUS_QUEUED) {
            return;
        }

        $audit->markRunning();

        try {
            $report = $service->audit(
                $audit->website_id,
                $audit->page_url,
                $audit->target_keyword !== '' ? $audit->target_keyword : null,
                true,
                $audit->serp_sample_gl,
            );
        } catch (Throwable $e) {
            Log::error('RunCustomPageAudit: service threw', [
                'audit_id' => $audit->id,
                'exception' => $e->getMessage(),
            ]);
            $audit->markFailed('Audit failed: '.$e->getMessage());

            return;
        }

        if ($report->status === 'completed') {
            $audit->markCompleted($report);

            return;
        }

        $audit->markFailed(
            $report->error_message !== null && $report->error_message !== ''
                ? $report->error_message
                : 'Audit did not complete.',
            $report,
        );
    }

    /**
     * Called by the queue worker when the job throws past the retry budget
     * (also covers timeouts). Keeps the UI row from staying stuck on "Running…".
     */
    public function failed(Throwable $e): void
    {
        $audit = CustomPageAudit::query()->find($this->auditId);
        if (! $audit instanceof CustomPageAudit) {
            return;
        }
        if ($audit->status === CustomPageAudit::STATUS_COMPLETED) {
            return;
        }
        $audit->markFailed('Audit failed: '.$e->getMessage());
    }
}
