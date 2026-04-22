<?php

namespace App\Console\Commands;

use App\Jobs\FetchCompetitorBacklinks;
use App\Models\CompetitorBacklink;
use App\Models\PageAuditReport;
use App\Services\CompetitorBacklinkService;
use Illuminate\Console\Command;

/**
 * Backfill competitor backlinks for existing audits, or force a refresh for
 * specific domains.
 *
 * Options mirror the other ebq:* maintenance commands:
 *   --audit=ID       — only the competitors on this one audit report
 *   --website=ID     — every completed audit for a website
 *   --domain=xxx     — force-refresh a specific competitor domain
 *   --force          — ignore freshness cache (re-bill)
 *   --sync           — fetch inline instead of queueing (good for debugging)
 *   --dry-run        — print the candidate list without acting
 *
 * Example: php artisan ebq:fetch-competitor-backlinks --audit=42 --sync
 */
class FetchCompetitorBacklinksCommand extends Command
{
    protected $signature = 'ebq:fetch-competitor-backlinks
                            {--audit= : Backfill competitor domains from one audit ID}
                            {--website= : Backfill across every completed audit for a website}
                            {--domain=* : Force-refresh one or more specific competitor domains}
                            {--force : Ignore the freshness cache}
                            {--sync : Fetch synchronously rather than queueing}
                            {--dry-run : Print the plan without dispatching or writing}';

    protected $description = 'Fetch or refresh competitor backlinks for one audit, one website, or specific domains.';

    public function handle(CompetitorBacklinkService $service): int
    {
        $domains = $this->collectDomains();

        if ($domains === []) {
            $this->warn('No competitor domains found. Check --audit / --website / --domain options.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        if (! $force) {
            $before = count($domains);
            $domains = array_values(array_filter($domains, fn ($d) => ! $service->isFresh($d)));
            if ($before !== count($domains)) {
                $this->line(sprintf('<fg=yellow>Skipping %d already-fresh domain(s).</> Use --force to override.', $before - count($domains)));
            }
        }

        if ($domains === []) {
            $this->info('All candidate domains are already fresh. Nothing to do.');

            return self::SUCCESS;
        }

        $this->line('<fg=yellow>Plan:</> '.count($domains).' domain(s)'.($force ? ' (forced)' : '').':');
        foreach (array_slice($domains, 0, 20) as $d) {
            $this->line('  · '.$d);
        }
        if (count($domains) > 20) {
            $this->line('  … and '.(count($domains) - 20).' more.');
        }

        if ((bool) $this->option('dry-run')) {
            $this->comment('Dry run — nothing dispatched.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('sync')) {
            $total = 0;
            foreach ($domains as $i => $domain) {
                $this->line(sprintf('  → [%d/%d] %s', $i + 1, count($domains), $domain));
                $written = $service->refresh($domain);
                $this->line(sprintf('    wrote %d row(s)', $written));
                $total += $written;
            }
            $this->info(sprintf('Done. %d backlink row(s) written for %d domain(s).', $total, count($domains)));
            if ($total === 0) {
                $this->warn('Zero rows written. Check DATAFORSEO_LOGIN/DATAFORSEO_PASSWORD in .env and storage/logs/laravel.log.');
            }

            return self::SUCCESS;
        }

        FetchCompetitorBacklinks::dispatch($domains);
        $this->info(sprintf('Queued %d domain(s). Ensure a queue worker is running (`php artisan queue:work`) for the job to process.', count($domains)));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function collectDomains(): array
    {
        $set = [];

        // --domain=foo.com,bar.com
        foreach ((array) $this->option('domain') as $raw) {
            foreach (preg_split('/[,\s]+/', (string) $raw) as $piece) {
                $d = CompetitorBacklink::extractDomain((string) $piece);
                if ($d !== '') {
                    $set[$d] = true;
                }
            }
        }

        // --audit=123
        if ($auditId = $this->option('audit')) {
            $report = PageAuditReport::query()->find((int) $auditId);
            if (! $report) {
                $this->error("Audit #{$auditId} not found.");

                return [];
            }
            foreach ($this->domainsFromReport($report) as $d) {
                $set[$d] = true;
            }
        }

        // --website=42
        if ($websiteId = $this->option('website')) {
            PageAuditReport::query()
                ->where('website_id', (int) $websiteId)
                ->where('status', 'completed')
                ->orderByDesc('id')
                ->limit(500)
                ->get(['id', 'result'])
                ->each(function (PageAuditReport $r) use (&$set) {
                    foreach ($this->domainsFromReport($r) as $d) {
                        $set[$d] = true;
                    }
                });
        }

        return array_keys($set);
    }

    /**
     * @return list<string>
     */
    private function domainsFromReport(PageAuditReport $report): array
    {
        $out = [];
        $competitors = data_get($report->result, 'benchmark.competitors', []);
        if (! is_array($competitors)) {
            return [];
        }
        foreach ($competitors as $row) {
            if (! is_array($row) || empty($row['url']) || ! is_string($row['url'])) {
                continue;
            }
            $d = CompetitorBacklink::extractDomain($row['url']);
            if ($d !== '') {
                $out[] = $d;
            }
        }

        return $out;
    }
}
