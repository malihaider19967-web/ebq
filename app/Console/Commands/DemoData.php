<?php

namespace App\Console\Commands;

use App\Models\ClientActivity;
use App\Models\KeywordMetric;
use App\Models\Website;
use App\Services\Demo\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Seed or remove the ebq.io demo dataset (owned by user id 1).
 *
 *   php artisan ebq:demo-data seed     # wipe + regenerate
 *   php artisan ebq:demo-data clear    # remove everything
 */
class DemoData extends Command
{
    protected $signature = 'ebq:demo-data {action : seed|clear} {--force : Skip the confirmation prompt for clear}';

    protected $description = 'Seed or remove the ebq.io demo dataset for user id 1';

    public function handle(DemoDataSeeder $seeder): int
    {
        $action = (string) $this->argument('action');

        if (! in_array($action, ['seed', 'clear'], true)) {
            $this->error("Unknown action '{$action}'. Use 'seed' or 'clear'.");
            return self::FAILURE;
        }

        if ($action === 'clear') {
            return $this->doClear($seeder);
        }

        return $this->doSeed($seeder);
    }

    private function doSeed(DemoDataSeeder $seeder): int
    {
        $this->info('Seeding demo data for '.DemoDataSeeder::DEMO_DOMAIN.' (user id '.DemoDataSeeder::DEMO_USER_ID.')…');

        try {
            $website = $seeder->seed();
        } catch (Throwable $e) {
            $this->error('Seeding failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done. Website id: '.$website->id);
        $this->table(
            ['Table', 'Rows'],
            [
                ['search_console_data', $this->count('search_console_data', $website->id)],
                ['analytics_data', $this->count('analytics_data', $website->id)],
                ['keyword_metrics (demo)', KeywordMetric::where('data_source', DemoDataSeeder::DEMO_KW_SOURCE)->count()],
                ['rank_tracking_keywords', $this->count('rank_tracking_keywords', $website->id)],
                ['rank_tracking_snapshots', DB::table('rank_tracking_snapshots')
                    ->whereIn('rank_tracking_keyword_id', DB::table('rank_tracking_keywords')->where('website_id', $website->id)->pluck('id'))
                    ->count()],
                ['page_indexing_statuses', $this->count('page_indexing_statuses', $website->id)],
                ['backlinks', $this->count('backlinks', $website->id)],
                ['page_audit_reports', $this->count('page_audit_reports', $website->id)],
                ['custom_page_audits', $this->count('custom_page_audits', $website->id)],
                ['writer_projects', $this->count('writer_projects', $website->id)],
                ['client_activities', ClientActivity::where('website_id', $website->id)->count()],
                ['redirect_suggestions', $this->count('redirect_suggestions', $website->id)],
                ['ai_insights', $this->count('ai_insights', $website->id)],
            ],
        );

        return self::SUCCESS;
    }

    private function doClear(DemoDataSeeder $seeder): int
    {
        $demo = Website::where('domain', DemoDataSeeder::DEMO_DOMAIN)
            ->where('user_id', DemoDataSeeder::DEMO_USER_ID)
            ->first();

        if (! $demo && KeywordMetric::where('data_source', DemoDataSeeder::DEMO_KW_SOURCE)->doesntExist()) {
            $this->info('No demo data found. Nothing to remove.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            'This deletes the '.DemoDataSeeder::DEMO_DOMAIN.' demo website (id '.($demo?->id ?? 'n/a').') and all its data. Continue?'
        )) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        try {
            $seeder->clear();
        } catch (Throwable $e) {
            $this->error('Clear failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Demo data removed.');
        return self::SUCCESS;
    }

    private function count(string $table, int $websiteId): int
    {
        return DB::table($table)->where('website_id', $websiteId)->count();
    }
}
