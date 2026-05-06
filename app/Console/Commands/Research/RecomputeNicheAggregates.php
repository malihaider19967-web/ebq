<?php

namespace App\Console\Commands\Research;

use App\Jobs\Research\NicheAggregateRecomputeJob;
use Illuminate\Console\Command;

class RecomputeNicheAggregates extends Command
{
    protected $signature = 'ebq:niche-aggregates-recompute {--sync : Recompute synchronously instead of dispatching}';

    protected $description = 'Recompute the anonymised niche_aggregates table (n>=3 sample floor).';

    public function handle(): int
    {
        if ((bool) $this->option('sync')) {
            app(\App\Services\Research\NicheAggregateRecomputeService::class)->recompute();
            $this->info('Recomputed niche_aggregates synchronously.');

            return self::SUCCESS;
        }

        NicheAggregateRecomputeJob::dispatch();
        $this->info('Dispatched NicheAggregateRecomputeJob.');

        return self::SUCCESS;
    }
}
