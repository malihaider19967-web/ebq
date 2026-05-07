<?php

namespace App\Jobs\Research;

use App\Models\Research\CompetitorScan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Spawns the Python competitor scraper as a subprocess. The Python tool
 * writes progress + final state directly to the same `competitor_scans`
 * row this job was dispatched for, so we don't need to stream output
 * back to the queue worker — we just wait for the process and capture
 * stderr if it dies.
 *
 * Long timeout: scrapes can run for the better part of an hour. Tries=1
 * because re-running a scrape is an operator decision (cost + politeness).
 */
class RunCompetitorScanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout;

    public function __construct(public int $scanId)
    {
        $this->timeout = (int) (config('research.scraper.timeout_seconds') ?: 3600);
    }

    public function handle(): void
    {
        $scan = CompetitorScan::query()->find($this->scanId);
        if ($scan === null) {
            Log::warning('RunCompetitorScanJob: scan not found', ['id' => $this->scanId]);

            return;
        }

        if (! in_array($scan->status, [CompetitorScan::STATUS_QUEUED, CompetitorScan::STATUS_RUNNING], true)) {
            Log::info('RunCompetitorScanJob: scan not in runnable state', ['id' => $this->scanId, 'status' => $scan->status]);

            return;
        }

        $python = (string) config('research.scraper.python_path', 'python');
        $cwd = (string) config('research.scraper.cwd', base_path('tools/competitor-scraper'));

        // Force the scratch SQLite into a location every queue-worker
        // user can write to. storage/app is conventionally owned by
        // www-data with group sticky bits set; using ./out from the
        // tool dir runs into permission-mismatch headaches when an
        // operator first runs the scraper manually as root.
        $outDir = storage_path('app/research-scraper');
        if (! is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }

        $process = new Process(
            [$python, '-m', 'competitor_scraper', 'run', '--scan-id', (string) $this->scanId],
            $cwd,
            ['COMPETITOR_SCRAPER_OUT_DIR' => $outDir],
            null,
            $this->timeout,
        );

        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->markFailed($scan, 'Could not start scraper: '.$e->getMessage());

            return;
        }

        if (! $process->isSuccessful()) {
            $stderr = trim((string) $process->getErrorOutput());
            $stdout = trim((string) $process->getOutput());
            $this->markFailed($scan, $this->summarise($stderr, $stdout, $process->getExitCode()));

            return;
        }

        // The Python tool already marked status=done after a clean flush;
        // we don't overwrite it here. Just refresh the row in case the
        // queue worker is logging.
        $scan->refresh();
        Log::info('RunCompetitorScanJob: completed', [
            'id' => $this->scanId,
            'status' => $scan->status,
            'page_count' => $scan->page_count,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $scan = CompetitorScan::query()->find($this->scanId);
        if ($scan === null) {
            return;
        }
        $this->markFailed($scan, 'Job failed: '.$e->getMessage());
    }

    private function markFailed(CompetitorScan $scan, string $error): void
    {
        $scan->forceFill([
            'status' => CompetitorScan::STATUS_FAILED,
            'finished_at' => Carbon::now(),
            'error' => mb_substr($error, 0, 65535),
        ])->save();
    }

    private function summarise(string $stderr, string $stdout, ?int $exitCode): string
    {
        $tail = trim($stderr !== '' ? $stderr : $stdout);
        $tail = mb_substr($tail, max(0, mb_strlen($tail) - 4096));

        return sprintf("scraper exited with code %s\n%s", $exitCode ?? '?', $tail);
    }
}
