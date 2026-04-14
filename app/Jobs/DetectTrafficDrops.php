<?php

namespace App\Jobs;

use App\Models\Website;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class DetectTrafficDrops implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $websiteId)
    {
    }

    public function handle(): void
    {
        $website = Website::with('user')->findOrFail($this->websiteId);
        // Placeholder for anomaly detection threshold logic.
        Notification::route('mail', $website->user->email)
            ->notify(new \App\Notifications\TrafficDropAlert($website));
    }
}
