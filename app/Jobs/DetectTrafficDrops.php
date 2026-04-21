<?php

namespace App\Jobs;

use App\Models\Website;
use App\Notifications\TrafficDropAlert;
use App\Services\TrafficAnomalyDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class DetectTrafficDrops implements ShouldQueue
{
    use Queueable;

    public const DEDUPE_HOURS = 24;

    public function __construct(public int $websiteId)
    {
    }

    public function handle(TrafficAnomalyDetector $detector): void
    {
        $website = Website::with('user')->find($this->websiteId);
        if (! $website) {
            return;
        }

        $result = $detector->detect($this->websiteId);
        if (! $result['has_anomaly']) {
            return;
        }

        if ($website->last_traffic_drop_alert_at
            && $website->last_traffic_drop_alert_at->gt(Carbon::now()->subHours(self::DEDUPE_HOURS))) {
            return;
        }

        $recipients = $website->getReportRecipientUsers();
        foreach ($recipients as $user) {
            $user->notify(new TrafficDropAlert($website, $result));
        }

        $website->forceFill(['last_traffic_drop_alert_at' => Carbon::now()])->save();
    }
}
