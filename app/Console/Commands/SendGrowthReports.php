<?php

namespace App\Console\Commands;

use App\Mail\GrowthReportMail;
use App\Models\Website;
use App\Services\ReportDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendGrowthReports extends Command
{
    protected $signature = 'ebq:send-reports';

    protected $description = 'Queue one EBQ daily growth report email per website recipient, snapped to the most recent fully-synced GSC day.';

    public function handle(ReportDataService $reports): int
    {
        $mailsQueued = 0;
        $sitesProcessed = 0;
        $sitesSkippedNoData = 0;
        $sitesSkippedNoRecipients = 0;

        Website::query()->with('owner')->chunkById(100, function ($websites) use ($reports, &$mailsQueued, &$sitesProcessed, &$sitesSkippedNoData, &$sitesSkippedNoRecipients) {
            foreach ($websites as $website) {
                // Snap the report to the most recent GSC date that is
                // both (a) present in our data and (b) at least
                // config('reports.gsc_lag_days') old. Without this,
                // the email compared two partial days and misreported
                // progress as negative even on up-days.
                $safeDate = $reports->lastSafeReportDate($website->id);
                if (! $safeDate) {
                    Log::info('ebq:send-reports: skipped — no usable GSC data', [
                        'website_id' => $website->id,
                        'domain' => $website->domain,
                    ]);
                    $sitesSkippedNoData++;
                    continue;
                }

                $date = $safeDate->toDateString();
                $recipients = $website->getReportRecipientUsers();
                if ($recipients->isEmpty()) {
                    $sitesSkippedNoRecipients++;
                    continue;
                }

                foreach ($recipients as $recipient) {
                    Mail::to($recipient->email)->queue(
                        new GrowthReportMail($recipient, $website, $date, $date, 'daily')
                    );
                    $mailsQueued++;
                }
                $sitesProcessed++;
            }
        });

        $this->info(sprintf(
            'Growth reports: %d email(s) queued for %d site(s); skipped %d (no GSC data) + %d (no recipients).',
            $mailsQueued,
            $sitesProcessed,
            $sitesSkippedNoData,
            $sitesSkippedNoRecipients,
        ));

        return self::SUCCESS;
    }
}
