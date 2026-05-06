<?php

namespace App\Console\Commands\Research;

use Illuminate\Console\Command;

/**
 * Diagnostic for the staged-rollout gate. Does NOT mutate .env — runtime
 * config edits would surprise other workers. Operator flips the gate by
 * setting RESEARCH_ROLLOUT_MODE / RESEARCH_ROLLOUT_ALLOWLIST in their
 * deployment config and restarting workers; this command tells them
 * what those settings currently resolve to and which website ids are
 * eligible.
 */
class ResearchRollout extends Command
{
    protected $signature = 'ebq:research-rollout
                            {--check= : Print whether website ID would currently be admitted}';

    protected $description = 'Inspect the research rollout gate (mode, allowlist) and check a specific website ID.';

    public function handle(): int
    {
        $mode = (string) config('research.rollout.mode', 'ga');
        $allowlist = array_values(array_map('intval', (array) config('research.rollout.allowlist', [])));

        $this->info("Mode: {$mode}");
        $this->info('Allowlist: '.($allowlist === [] ? '(empty)' : implode(', ', $allowlist)));

        $check = $this->option('check');
        if ($check !== null && $check !== '') {
            $id = (int) $check;
            $admitted = $mode === 'ga' || in_array($id, $allowlist, true);
            $this->line('Website #'.$id.': '.($admitted ? '<fg=green>admitted</>' : '<fg=yellow>blocked (not in allowlist)</>'));
        }

        return self::SUCCESS;
    }
}
