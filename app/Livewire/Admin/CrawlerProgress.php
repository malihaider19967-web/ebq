<?php

namespace App\Livewire\Admin;

use App\Models\CrawlFinding;
use App\Models\CrawlRun;
use App\Models\CrawlSite;
use App\Models\WebsitePage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Component;

/**
 * Fleet-wide crawler progress for the admin panel: every shared crawl_site, its
 * current run state + progress, crawled pages, open findings, health, and the
 * live crawl-queue depth. Polls so it updates as crawls run.
 */
class CrawlerProgress extends Component
{
    public function render()
    {
        // Eager-load the subscriber websites + their owners so the admin can see
        // exactly which website(s)/client(s) each shared crawl belongs to.
        $sites = CrawlSite::with(['websites:id,crawl_site_id,domain,user_id', 'websites.user:id,name,email'])
            ->orderBy('normalized_domain')->get();
        $ids = $sites->pluck('id');

        // Latest run per crawl_site (one query for the ids, one to load them).
        $latestIds = CrawlRun::whereIn('crawl_site_id', $ids)
            ->select(DB::raw('MAX(id) as id'))->groupBy('crawl_site_id')->pluck('id');
        $runs = CrawlRun::whereIn('id', $latestIds)->get()->keyBy('crawl_site_id');

        $crawled = WebsitePage::whereIn('crawl_site_id', $ids)->whereNotNull('last_crawled_at')
            ->select('crawl_site_id', DB::raw('COUNT(*) as c'))->groupBy('crawl_site_id')->pluck('c', 'crawl_site_id');
        $totalPages = WebsitePage::whereIn('crawl_site_id', $ids)
            ->select('crawl_site_id', DB::raw('COUNT(*) as c'))->groupBy('crawl_site_id')->pluck('c', 'crawl_site_id');
        $openFindings = CrawlFinding::whereIn('crawl_site_id', $ids)->where('status', 'open')
            ->select('crawl_site_id', DB::raw('COUNT(*) as c'))->groupBy('crawl_site_id')->pluck('c', 'crawl_site_id');

        $rows = $sites->map(function (CrawlSite $cs) use ($runs, $crawled, $totalPages, $openFindings): array {
            $run = $runs[$cs->id] ?? null;

            return [
                'domain' => $cs->normalized_domain,
                'subscribers' => (int) $cs->subscriber_count,
                // Which website(s)/client(s) this shared crawl serves.
                'clients' => $cs->websites->map(fn ($w) => [
                    'website' => $w->domain,
                    'owner' => $w->user?->name ?: ($w->user?->email ?? '—'),
                ])->all(),
                'cap' => (int) $cs->effective_cap,
                'status' => $run?->status ?? 'never',
                'trigger' => $run?->trigger,
                'seen' => (int) ($run->pages_seen ?? 0),
                'fetched' => (int) ($run->pages_fetched ?? 0),
                'errors' => (int) ($run->pages_error ?? 0),
                'crawled' => (int) ($crawled[$cs->id] ?? 0),
                'frontier' => (int) ($totalPages[$cs->id] ?? 0),
                'findings' => (int) ($openFindings[$cs->id] ?? 0),
                'health' => $run?->health_score,
                'started_at' => $run?->started_at,
                'finished_at' => $run?->finished_at,
            ];
        })->all();

        $running = collect($rows)->whereIn('status', [CrawlRun::STATUS_RUNNING, CrawlRun::STATUS_FINALIZING])->count();

        return view('livewire.admin.crawler-progress', [
            'rows' => $rows,
            'summary' => [
                'sites' => count($rows),
                'running' => $running,
                'completed' => collect($rows)->where('status', CrawlRun::STATUS_COMPLETED)->count(),
                'blocked' => collect($rows)->where('status', CrawlRun::STATUS_ABORTED)->count(),
                'never' => collect($rows)->where('status', 'never')->count(),
                'crawled_pages' => collect($rows)->sum('crawled'),
                'open_findings' => collect($rows)->sum('findings'),
                'queue_depth' => $this->crawlQueueDepth(),
            ],
        ]);
    }

    /** Live crawl-queue backlog (0 on any error so the page never breaks). */
    private function crawlQueueDepth(): int
    {
        try {
            return (int) Queue::connection('redis')->size('crawl');
        } catch (\Throwable) {
            return 0;
        }
    }
}
