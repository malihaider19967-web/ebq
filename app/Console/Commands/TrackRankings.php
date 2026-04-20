<?php

namespace App\Console\Commands;

use App\Jobs\TrackKeywordRankJob;
use App\Models\RankTrackingKeyword;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TrackRankings extends Command
{
    protected $signature = 'ebq:track-rankings {--force : Dispatch every active keyword regardless of schedule}';

    protected $description = 'Dispatch SERP rank checks for keywords whose next_check_at has elapsed';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $now = Carbon::now();

        $query = RankTrackingKeyword::query()->where('is_active', true);
        if (! $force) {
            $query->where(function ($q) use ($now) {
                $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', $now);
            });
        }

        $count = 0;
        $query->select('id')->chunkById(200, function ($rows) use (&$count, $force) {
            foreach ($rows as $row) {
                TrackKeywordRankJob::dispatch((int) $row->id, $force);
                $count++;
            }
        });

        $this->info("Dispatched {$count} rank-tracking job(s).");

        return self::SUCCESS;
    }
}
