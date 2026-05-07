<?php

namespace App\Console\Commands\Research;

use App\Jobs\Research\RunCompetitorScanJob;
use App\Models\Research\CompetitorScan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Ad-hoc CLI for triggering a competitor scan from the terminal. The
 * canonical path is the admin UI; this exists for ops debugging.
 */
class CompetitorScrape extends Command
{
    protected $signature = 'ebq:competitor-scrape
                            {seedUrl : Seed URL, e.g. https://competitor.com}
                            {--website= : Optional website ID to attach the scan to}
                            {--keywords= : Comma-separated seed keywords}
                            {--max-pages=250 : max_total_pages cap}
                            {--max-depth=4 : max_depth cap}
                            {--sync : Run synchronously instead of dispatching}';

    protected $description = 'Queue a competitor scrape (canonical path is the admin UI).';

    public function handle(): int
    {
        $seedUrl = (string) $this->argument('seedUrl');
        $host = parse_url($seedUrl, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $this->error('Could not parse host from seed URL.');

            return self::FAILURE;
        }
        $seedDomain = preg_replace('/^www\./', '', mb_strtolower($host));

        if (CompetitorScan::query()->where('seed_domain', $seedDomain)->active()->exists()) {
            $this->error("A scan for {$seedDomain} is already in progress.");

            return self::FAILURE;
        }

        $seeds = [];
        $kwOption = (string) ($this->option('keywords') ?? '');
        if ($kwOption !== '') {
            $seeds = array_values(array_unique(array_filter(array_map('trim', explode(',', $kwOption)))));
        }

        $scan = CompetitorScan::create([
            'website_id' => $this->option('website') ? (int) $this->option('website') : null,
            'triggered_by_user_id' => Auth::id(),
            'seed_domain' => $seedDomain,
            'seed_url' => $seedUrl,
            'seed_keywords' => $seeds,
            'caps' => [
                'max_total_pages' => max(10, (int) $this->option('max-pages')),
                'max_depth' => max(1, (int) $this->option('max-depth')),
            ],
            'status' => CompetitorScan::STATUS_QUEUED,
        ]);

        if ((bool) $this->option('sync')) {
            (new RunCompetitorScanJob($scan->id))->handle();
            $this->info("Scan #{$scan->id} completed synchronously.");

            return self::SUCCESS;
        }

        RunCompetitorScanJob::dispatch($scan->id);
        $this->info("Scan #{$scan->id} queued. Track at /admin/research/competitor-scans/{$scan->id}.");

        return self::SUCCESS;
    }
}
