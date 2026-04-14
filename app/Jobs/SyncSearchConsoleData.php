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

    public function __construct(public int $websiteId)
    {
    }

    public function handle(SearchConsoleService $service): void
    {
        $website = Website::findOrFail($this->websiteId);
        $account = $website->user->googleAccounts()->latest()->first();

        if (! $account) {
            Log::warning("SyncSearchConsoleData: No Google account for website {$this->websiteId}");
            return;
        }

        $rows = $service->fetchSearchAnalytics(
            $account,
            $website->gsc_site_url,
            Carbon::now()->subDays(30)->toDateString(),
            Carbon::now()->toDateString()
        );

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
