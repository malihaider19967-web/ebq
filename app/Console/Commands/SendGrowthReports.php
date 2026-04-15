<?php

namespace App\Console\Commands;

use App\Mail\GrowthReportMail;
use App\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendGrowthReports extends Command
{
    protected $signature = 'growthhub:send-reports';

    protected $description = 'Queue one GrowthHub report email per website (owner) for yesterday in the app timezone';

    public function handle(): int
    {
        $reportDate = Carbon::yesterday(config('app.timezone'));

        Website::query()->with('owner')->chunkById(100, function ($websites) use ($reportDate) {
            foreach ($websites as $website) {
                $owner = $website->owner;
                if (! $owner) {
                    continue;
                }

                Mail::to($owner->email)->queue(new GrowthReportMail($owner, $website, $reportDate));
            }
        });

        $this->info('Growth reports queued (one per website).');

        return self::SUCCESS;
    }
}
