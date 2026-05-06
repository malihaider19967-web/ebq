<?php

namespace App\Console\Commands\Research;

use App\Jobs\Research\DetectResearchSignalsJob;
use Illuminate\Console\Command;

class DetectResearchSignals extends Command
{
    protected $signature = 'ebq:detect-research-signals
                            {--website=* : Limit scan to one or more website IDs}
                            {--sync : Run synchronously instead of dispatching to the queue}';

    protected $description = 'Emit ranking_drop / serp_change / volatility_spike / new_opportunity alerts into keyword_alerts.';

    public function handle(): int
    {
        $ids = $this->option('website') ?: null;
        if (is_array($ids)) {
            $ids = array_values(array_filter(array_map('intval', $ids), fn ($n) => $n > 0));
            if ($ids === []) {
                $ids = null;
            }
        }

        if ((bool) $this->option('sync')) {
            (new DetectResearchSignalsJob($ids))->handle(app(\App\Services\Research\Intelligence\OpportunityEngine::class));
            $this->info('Detected research signals synchronously.');

            return self::SUCCESS;
        }

        DetectResearchSignalsJob::dispatch($ids);
        $this->info('Dispatched DetectResearchSignalsJob.');

        return self::SUCCESS;
    }
}
