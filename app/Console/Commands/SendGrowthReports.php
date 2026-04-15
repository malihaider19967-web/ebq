<?php

namespace App\Console\Commands;

use App\Mail\GrowthReportMail;
use App\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendGrowthReports extends Command
{
    protected $signature = 'ebq:send-reports';

    protected $description = 'Queue one EBQ report email per website recipient for yesterday in the app timezone';

    public function handle(): int
    {
        $yesterday = Carbon::yesterday(config('app.timezone'))->toDateString();

        Website::query()->with('owner')->chunkById(100, function ($websites) use ($yesterday) {
            foreach ($websites as $website) {
                $recipients = $website->getReportRecipientUsers();

                foreach ($recipients as $recipient) {
                    Mail::to($recipient->email)->queue(
                        new GrowthReportMail($recipient, $website, $yesterday, $yesterday, 'daily')
                    );
                }
            }
        });

        $this->info('Growth reports queued.');

        return self::SUCCESS;
    }
}
