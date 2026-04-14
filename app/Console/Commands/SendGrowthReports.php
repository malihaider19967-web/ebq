<?php

namespace App\Console\Commands;

use App\Mail\GrowthReportMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendGrowthReports extends Command
{
    protected $signature = 'growthhub:send-reports';
    protected $description = 'Send daily GrowthHub report email';

    public function handle(): int
    {
        User::query()->chunkById(100, function ($users) {
            foreach ($users as $user) {
                Mail::to($user->email)->queue(new GrowthReportMail($user));
            }
        });

        $this->info('Growth reports queued.');
        return self::SUCCESS;
    }
}
