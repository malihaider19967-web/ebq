<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\Google\SearchConsoleService;
use App\Support\UrlNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncSearchConsoleData implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(
        public int $websiteId,
        public int $days = 30,
    ) {}

    public function handle(SearchConsoleService $service): void
    {
        $website = Website::findOrFail($this->websiteId);
        $account = $website->user->googleAccounts()->latest()->first();

        if (! $account) {
            Log::warning("SyncSearchConsoleData: No Google account for website {$this->websiteId}");
            return;
        }

        $end = Carbon::now();
        $cursor = Carbon::now()->subDays($this->days);

        while ($cursor->lt($end)) {
            $windowEnd = $cursor->copy()->addDays(6)->min($end);

            $rows = $service->fetchSearchAnalytics(
                $account,
                $website->gsc_site_url,
                $cursor->toDateString(),
                $windowEnd->toDateString()
            );

            $this->upsertRows($rows);

            $cursor->addDays(7);
        }
    }

    private function upsertRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $prepared = array_map(function (array $row) {
                $row['website_id'] = $this->websiteId;
                $row['page'] = UrlNormalizer::normalize($row['page']);
                $row['country'] = $row['country'] ?? '';
                $row['device'] = $row['device'] ?? '';
                return $row;
            }, $chunk);

            DB::table('search_console_data')->upsert(
                $prepared,
                ['website_id', 'date', 'query', 'page', 'country', 'device'],
                ['clicks', 'impressions', 'position', 'ctr', 'updated_at']
            );
        }
    }
}
