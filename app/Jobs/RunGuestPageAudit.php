<?php

namespace App\Jobs;

use App\Mail\GuestAuditLinkMail;
use App\Models\GuestPageAudit;
use App\Services\PageAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Runs an anonymous landing-page audit ({@see GuestPageAudit}) in the background.
 *
 * Constraints mirror {@see RunCustomPageAudit}:
 * - tries=1        : never auto-retry a paid/external fetch without a human.
 * - timeout=120    : guest audits are lite (no link checking / Serper / CWV),
 *                    so they finish well within this ceiling.
 * - uniqueFor=1800 : a double-submit can't run the same audit twice.
 */
class RunGuestPageAudit implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 1800;

    public function __construct(public readonly int $auditId)
    {
        $this->onQueue(\App\Support\Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return 'guest-page-audit:'.$this->auditId;
    }

    public function handle(PageAuditService $service): void
    {
        $audit = GuestPageAudit::query()->find($this->auditId);
        if (! $audit instanceof GuestPageAudit) {
            return; // row deleted before we got to it — nothing to do.
        }

        if ($audit->status !== GuestPageAudit::STATUS_QUEUED) {
            return; // already picked up by another worker.
        }

        $audit->markRunning();

        try {
            $outcome = $service->auditGuest($audit->url, $audit->keyword, $audit->serp_gl);
        } catch (Throwable $e) {
            Log::error('RunGuestPageAudit: service threw', [
                'audit_id' => $audit->id,
                'exception' => $e->getMessage(),
            ]);
            $audit->markFailed('Audit failed. Please try again.');

            return;
        }

        if (($outcome['status'] ?? null) === 'completed') {
            $audit->markCompleted($outcome);

            // Guests who supplied their email (2nd free audit) get the report link.
            if (is_string($audit->email) && $audit->email !== '') {
                try {
                    Mail::to($audit->email)->send(new GuestAuditLinkMail($audit->fresh()));
                } catch (Throwable $e) {
                    Log::warning('RunGuestPageAudit: failed to email audit link', [
                        'audit_id' => $audit->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            return;
        }

        $audit->markFailed(
            is_string($outcome['error_message'] ?? null) && $outcome['error_message'] !== ''
                ? $outcome['error_message']
                : 'Audit did not complete.',
            $outcome['http_status'] ?? null,
        );
    }

    /**
     * Queue worker calls this when the job throws past its retry budget (also
     * covers timeouts) — keeps the poller from waiting on a stuck "running" row.
     */
    public function failed(Throwable $e): void
    {
        $audit = GuestPageAudit::query()->find($this->auditId);
        if (! $audit instanceof GuestPageAudit) {
            return;
        }
        if ($audit->status === GuestPageAudit::STATUS_COMPLETED) {
            return;
        }
        $audit->markFailed('Audit failed. Please try again.');
    }
}
