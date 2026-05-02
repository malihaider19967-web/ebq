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

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(
        public int $websiteId,
        public int $days = 30,
    ) {}

    public function handle(GoogleAnalyticsService $service): void
    {
        $website = Website::findOrFail($this->websiteId);
        // Plan-limit freeze: when the website is past the owning user's
        // plan limit, skip the GA fetch. Avoids burning Google quota on
        // sites the user can't actually see in EBQ until they upgrade.
        if ($website->isFrozen()) {
            Log::info("SyncAnalyticsData: skipping frozen website {$this->websiteId}");
            return;
        }
        $account = $website->user->googleAccounts()->latest()->first();

        if (! $account) {
            Log::warning("SyncAnalyticsData: No Google account for website {$this->websiteId}");
            return;
        }

        $rows = $service->fetchDailyTraffic(
            $account,
            $website->ga_property_id,
            Carbon::now()->subDays($this->days)->toDateString(),
            Carbon::now()->toDateString()
        );

        if ($rows === []) {
            Website::whereKey($this->websiteId)->update(['last_analytics_sync_at' => now()]);

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

        Website::whereKey($this->websiteId)->update(['last_analytics_sync_at' => now()]);
    }
}
