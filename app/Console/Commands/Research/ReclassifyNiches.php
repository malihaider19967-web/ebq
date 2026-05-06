<?php

namespace App\Console\Commands\Research;

use App\Jobs\Research\ClassifyWebsiteNichesJob;
use App\Models\Website;
use Illuminate\Console\Command;

class ReclassifyNiches extends Command
{
    protected $signature = 'ebq:reclassify-niches
                            {--website= : Limit to one website ID}
                            {--dry-run : Print the website count without dispatching}';

    protected $description = 'Dispatch ClassifyWebsiteNichesJob for every website (monthly).';

    public function handle(): int
    {
        $websiteOption = $this->option('website');
        $dryRun = (bool) $this->option('dry-run');

        $query = Website::query();
        if ($websiteOption !== null && $websiteOption !== '') {
            $query->whereKey((int) $websiteOption);
        }

        $ids = $query->pluck('id');
        $count = $ids->count();

        $this->line("<fg=cyan>ReclassifyNiches:</> {$count} website(s) eligible.");

        if ($dryRun || $count === 0) {
            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            ClassifyWebsiteNichesJob::dispatch((int) $id);
        }

        $this->info("Dispatched {$count} ClassifyWebsiteNichesJob(s).");

        return self::SUCCESS;
    }
}
