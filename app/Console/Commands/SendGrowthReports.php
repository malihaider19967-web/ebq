<?php

namespace App\Console\Commands;

use App\Models\Website;
use App\Services\ReportDataService;
use App\Services\Reports\ReportMailDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendGrowthReports extends Command
{
    protected $signature = 'ebq:send-reports';

    protected $description = 'Queue one EBQ daily growth report email per website recipient, snapped to the most recent fully-synced GSC day.';

    public function handle(ReportDataService $reports, ReportMailDispatcher $dispatcher): int
    {
        $mailsQueued = 0;
        $sitesProcessed = 0;
        $sitesDegraded = 0;
        $sitesSkippedNoData = 0;
        $sitesSkippedNoRecipients = 0;

        Website::query()->with('owner')->chunkById(100, function ($websites) use ($reports, $dispatcher, &$mailsQueued, &$sitesProcessed, &$sitesDegraded, &$sitesSkippedNoData, &$sitesSkippedNoRecipients) {
            foreach ($websites as $website) {
                // Per-source readiness: a site connected to only one of
                // GA/GSC still gets a (degraded) report anchored to
                // whichever source has data. We skip only when NEITHER
                // source has any reportable data. The report is snapped to
                // the most recent safe day (GSC is lag-aware so partial
                // days don't read as regressions).
                $readiness = $reports->reportReadiness($website);
                if (! $readiness['any']) {
                    Log::info('ebq:send-reports: skipped — no usable GA or GSC data', [
                        'website_id' => $website->id,
                        'domain' => $website->domain,
                    ]);
                    $sitesSkippedNoData++;
                    continue;
                }

                if (! $readiness['ga'] || ! $readiness['gsc']) {
                    $sitesDegraded++;
                }

                $date = $readiness['date']->toDateString();
                $recipients = $website->getReportRecipientUsers();
                if ($recipients->isEmpty()) {
                    $sitesSkippedNoRecipients++;
                    continue;
                }

                foreach ($recipients as $recipient) {
                    // Dispatcher resolves branding + transport from the
                    // website owner's plan. Returns null when the plan
                    // disables `report_whitelabel` (= queued via the
                    // default mailer) or the resolved transport row when
                    // a per-tenant OAuth/SMTP route was used.
                    $dispatcher->send($recipient, $website, $date, $date, 'daily');
                    $mailsQueued++;
                }
                $sitesProcessed++;
            }
        });

        $this->info(sprintf(
            'Growth reports: %d email(s) queued for %d site(s) (%d degraded — only one source); skipped %d (no GA/GSC data) + %d (no recipients).',
            $mailsQueued,
            $sitesProcessed,
            $sitesDegraded,
            $sitesSkippedNoData,
            $sitesSkippedNoRecipients,
        ));

        return self::SUCCESS;
    }
}
