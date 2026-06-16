<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeSiteJob;
use App\Jobs\CrawlPassJob;
use App\Models\CrawlRun;
use App\Models\WebsitePage;
use App\Support\Queues;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Watchdog that recovers stalled crawl runs.
 *
 * The multi-pass loop continues only via the Bus::batch ->finally() callback. If a
 * worker is recycled mid-batch (queue:work --max-time) or jobs are lost, that
 * callback never fires and the run sits in `running`/`finalizing` forever — no
 * pages crawled, AnalyzeSiteJob never dispatched (observed: several backfill
 * domains wedged for hours). An active crawl bumps the run row continuously
 * (pages_seen/pages_fetched increments touch updated_at), so a run whose
 * updated_at is older than the stall window with work still to do is wedged.
 *
 * For each stalled run we either resume the chain (dispatch the next pass) or, if
 * the frontier is exhausted / the run is finalizing / it's been running too long,
 * finalize it (dispatch AnalyzeSiteJob). Idempotent-ish: re-dispatching a pass only
 * selects not-yet-fetched pages, and a resurrected original batch just re-crawls a
 * few pages (harmless upsert).
 */
class CrawlSupervisor extends Command
{
    protected $signature = 'ebq:crawl-supervisor';

    protected $description = 'Recover stalled crawl runs (dead multi-pass chain, or exhausted-but-not-finalized).';

    public function handle(): int
    {
        $stallMinutes = max(2, (int) config('crawler.stall_minutes', 15));
        $maxRunHours = max(1, (int) config('crawler.max_run_hours', 6));
        $cutoff = now()->subMinutes($stallMinutes);

        $runs = CrawlRun::whereIn('status', [CrawlRun::STATUS_RUNNING, CrawlRun::STATUS_FINALIZING])
            ->where('updated_at', '<', $cutoff)
            ->get();

        $resumed = 0;
        $finalized = 0;

        foreach ($runs as $run) {
            // A finalizing run that stalled = AnalyzeSiteJob died; re-dispatch it.
            if ($run->status === CrawlRun::STATUS_FINALIZING) {
                AnalyzeSiteJob::dispatch($run->id)->onQueue(Queues::CRAWL_FINALIZE);
                $finalized++;

                continue;
            }

            $crawlSite = $run->crawlSite;
            if (! $crawlSite) {
                continue;
            }

            // Give up resurrecting after a hard age cap — finalize with what we have
            // rather than re-kicking forever.
            $tooOld = $run->started_at && $run->started_at->lt(now()->subHours($maxRunHours));

            $maxPages = max(1, (int) $crawlSite->effective_cap ?: (int) config('crawler.max_pages_per_run', 200000));
            $hasMore = ! $tooOld
                && (int) $run->pages_seen < $maxPages
                && WebsitePage::where('crawl_site_id', $crawlSite->id)
                    ->whereNull('removed_at')
                    ->where(function ($q) use ($run): void {
                        $q->whereNull('last_crawled_at')->orWhere('last_crawled_at', '<', $run->started_at);
                    })
                    ->due()
                    ->exists();

            if ($hasMore) {
                CrawlPassJob::dispatch($run->id, $crawlSite->id, 1, false)->onQueue(Queues::CRAWL);
                $resumed++;
                Log::warning("CrawlSupervisor: resumed wedged run {$run->id} (crawl_site {$crawlSite->id}, idle since {$run->updated_at}).");
            } else {
                AnalyzeSiteJob::dispatch($run->id)->onQueue(Queues::CRAWL_FINALIZE);
                $finalized++;
                Log::warning("CrawlSupervisor: finalizing stalled run {$run->id} (crawl_site {$crawlSite->id}; exhausted=".($tooOld ? 'aged-out' : 'no-due-pages').').');
            }
        }

        $this->info("crawl-supervisor: checked {$runs->count()} stalled run(s) — resumed {$resumed}, finalized {$finalized}.");

        return self::SUCCESS;
    }
}
