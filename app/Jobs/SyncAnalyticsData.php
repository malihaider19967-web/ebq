<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\Google\GoogleAnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAnalyticsData implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $websiteId)
    {
    }

    public function handle(GoogleAnalyticsService $service): void
    {
        $website = Website::findOrFail($this->websiteId);
        $account = $website->user->googleAccounts()->latest()->first();

        if (! $account) {
            Log::warning("SyncAnalyticsData: No Google account for website {$this->websiteId}");
            return;
        }

        $rows = $service->fetchDailyTraffic(
            $account,
            $website->ga_property_id,
            Carbon::now()->subDays(30)->toDateString(),
            Carbon::now()->toDateString()
        );

        if ($rows === []) {
            return;
        }

        foreach ($rows as &$row) {
            $row['website_id'] = $this->websiteId;
        }

        DB::table('analytics_data')->upsert(
            $rows,
            ['website_id', 'date', 'source'],
            ['users', 'sessions', 'bounce_rate', 'updated_at']
        );
    }
}
