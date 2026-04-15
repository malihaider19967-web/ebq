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
        $yesterday = Carbon::yesterday(config('app.timezone'))->toDateString();

        Website::query()->with('owner')->chunkById(100, function ($websites) use ($yesterday) {
            foreach ($websites as $website) {
                $owner = $website->owner;
                if (! $owner) {
                    continue;
                }

                Mail::to($owner->email)->queue(
                    new GrowthReportMail($owner, $website, $yesterday, $yesterday, 'daily')
                );
            }
        });

        $this->info('Growth reports queued (one per website).');

        return self::SUCCESS;
    }
}
