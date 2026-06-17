<?php

namespace App\Jobs;

use App\Mail\GuestPageSpeedLinkMail;
use App\Models\GuestPageSpeed;
use App\Services\LighthouseClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Runs ONE strategy (mobile|desktop) of a public guest PageSpeed test.
 *
 * Two of these are dispatched per test so each strategy gets a full worker
 * cycle (~80s) rather than sharing one — heavy sites need 40s+ per strategy,
 * which is why a single combined job timed out. The two jobs run in parallel
 * on separate workers and coordinate via a row lock: whichever finishes the
 * pair finalizes the GuestPageSpeed row (and sends the email, if requested).
 */
class RunGuestPageSpeedStrategy implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 88; // < worker --timeout=90

    public int $uniqueFor = 1800;

    public function __construct(
        public readonly string $id,
        public readonly string $strategy,
    ) {
        $this->onQueue(\App\Support\Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return 'guest-page-speed:'.$this->id.':'.$this->strategy;
    }

    public function handle(LighthouseClient $lighthouse): void
    {
        $row = GuestPageSpeed::query()->find($this->id);
        if (! $row instanceof GuestPageSpeed) {
            return;
        }
        if ($row->status === GuestPageSpeed::STATUS_QUEUED) {
            $row->markRunning();
        }

        // Single strategy → can use the full per-call cap.
        $report = $lighthouse->fetchStrategyReport($row->url, $this->strategy);

        $this->record($report);
    }

    /** Timeout / exception: still record this strategy as failed so the pair can finalize. */
    public function failed(?\Throwable $e): void
    {
        $this->record(null);
    }

    /**
     * Merge this strategy's result into the row under a lock, finalizing the
     * test once both strategies have reported.
     *
     * @param  array<string, mixed>|null  $report
     */
    private function record(?array $report): void
    {
        $shouldEmail = false;
        $rowForEmail = null;

        DB::transaction(function () use ($report, &$shouldEmail, &$rowForEmail) {
            $row = GuestPageSpeed::query()->lockForUpdate()->find($this->id);
            if (! $row instanceof GuestPageSpeed || $row->isFinished()) {
                return;
            }

            $result = is_array($row->result) ? $row->result : [];
            $result[$this->strategy] = $report;
            $result['source'] = 'lighthouse-local';
            if ($report !== null && ! empty($report['lighthouse_version'])) {
                $result['lighthouse_version'] = $report['lighthouse_version'];
            }

            $bothReported = array_key_exists('mobile', $result) && array_key_exists('desktop', $result);
            if (! $bothReported) {
                $row->forceFill(['result' => $result, 'status' => GuestPageSpeed::STATUS_RUNNING])->save();

                return;
            }

            $anyOk = ($result['mobile'] ?? null) !== null || ($result['desktop'] ?? null) !== null;
            $result['fetched_at'] = now()->toIso8601String();
            $row->forceFill([
                'result' => $result,
                'status' => $anyOk ? GuestPageSpeed::STATUS_COMPLETED : GuestPageSpeed::STATUS_FAILED,
                'error_message' => $anyOk ? null : 'Could not measure that URL.',
            ])->save();

            $shouldEmail = $anyOk && (bool) $row->email;
            $rowForEmail = $row;
        });

        if ($shouldEmail && $rowForEmail) {
            try {
                Mail::to($rowForEmail->email)->send(new GuestPageSpeedLinkMail($rowForEmail));
            } catch (\Throwable $e) {
                Log::warning('RunGuestPageSpeedStrategy: email failed: '.$e->getMessage());
            }
        }
    }
}
