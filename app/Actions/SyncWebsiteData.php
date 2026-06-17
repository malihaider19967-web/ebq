<?php

namespace App\Actions;

use App\Jobs\SyncAnalyticsData;
use App\Jobs\SyncSearchConsoleData;

class SyncWebsiteData
{
    public function execute(string $websiteId): void
    {
        SyncAnalyticsData::dispatch($websiteId);
        SyncSearchConsoleData::dispatch($websiteId);
    }
}
