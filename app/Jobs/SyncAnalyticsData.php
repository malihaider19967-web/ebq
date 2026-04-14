<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\Google\GoogleAnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncAnalyticsData implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $websiteId)
    {
    }

    public function handle(GoogleAnalyticsService $service): void
    {
        $website = Website::findOrFail($this->websiteId);
        $rows = $service->fetchDailyTraffic(
            $website->ga_property_id,
            Carbon::now()->subDays(30)->toDateString(),
            Carbon::now()->toDateString()
        );

        if ($rows === []) {
            return;
        }

        DB::table('analytics_data')->upsert(
            $rows,
            ['website_id', 'date', 'source'],
            ['users', 'sessions', 'bounce_rate', 'updated_at']
        );
    }
}
