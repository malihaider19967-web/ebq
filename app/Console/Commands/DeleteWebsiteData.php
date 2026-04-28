<?php

namespace App\Console\Commands;

use App\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeleteWebsiteData extends Command
{
    protected $signature = 'ebq:delete-website-data
                            {website : Website ID to delete}
                            {--dry-run : Print what would be deleted without deleting}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete a website and all data related to it.';

    public function handle(): int
    {
        $websiteId = (int) $this->argument('website');
        if ($websiteId <= 0) {
            $this->error('Website ID must be a positive integer.');

            return self::FAILURE;
        }

        $website = Website::query()->find($websiteId);
        if (! $website) {
            $this->error("Website #{$websiteId} not found.");

            return self::FAILURE;
        }

        $plan = $this->buildPlan($websiteId);
        $this->line('');
        $this->line("<fg=yellow>Delete plan for website #{$website->id} ({$website->domain}):</>");
        $this->line('');
        foreach ($plan as $row) {
            $this->line(sprintf('  %-28s %s', $row['label'], number_format($row['count'])));
        }
        $this->line('');

        $total = array_sum(array_column($plan, 'count'));
        if ($total === 0) {
            $this->comment('No related rows found, but website record will still be deleted.');
        }

        if ((bool) $this->option('dry-run')) {
            $this->comment('Dry run complete — nothing deleted.');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force')) {
            $ok = $this->confirm(
                "Delete website #{$website->id} ({$website->domain}) and all related data? This cannot be undone.",
                false
            );
            if (! $ok) {
                $this->comment('Aborted.');

                return self::SUCCESS;
            }
        }

        DB::transaction(function () use ($website): void {
            // This table uses nullOnDelete, so remove website-scoped rows explicitly.
            DB::table('client_activities')->where('website_id', $website->id)->delete();

            // Deleting website cascades to all website-bound tables via FKs.
            $website->delete();
        });

        Cache::forget("top_countries:{$websiteId}");
        Cache::forget("country_filter:{$websiteId}");
        Cache::forget("insights:counts:{$websiteId}:all");

        $this->info("Website #{$websiteId} and related data deleted.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function buildPlan(int $websiteId): array
    {
        return [
            ['label' => 'website', 'count' => DB::table('websites')->where('id', $websiteId)->count()],
            ['label' => 'analytics_data', 'count' => DB::table('analytics_data')->where('website_id', $websiteId)->count()],
            ['label' => 'search_console_data', 'count' => DB::table('search_console_data')->where('website_id', $websiteId)->count()],
            ['label' => 'ai_insights', 'count' => DB::table('ai_insights')->where('website_id', $websiteId)->count()],
            ['label' => 'backlinks', 'count' => DB::table('backlinks')->where('website_id', $websiteId)->count()],
            ['label' => 'page_audit_reports', 'count' => DB::table('page_audit_reports')->where('website_id', $websiteId)->count()],
            ['label' => 'custom_page_audits', 'count' => DB::table('custom_page_audits')->where('website_id', $websiteId)->count()],
            ['label' => 'page_indexing_statuses', 'count' => DB::table('page_indexing_statuses')->where('website_id', $websiteId)->count()],
            ['label' => 'rank_tracking_keywords', 'count' => DB::table('rank_tracking_keywords')->where('website_id', $websiteId)->count()],
            ['label' => 'website_invitations', 'count' => DB::table('website_invitations')->where('website_id', $websiteId)->count()],
            ['label' => 'website_user', 'count' => DB::table('website_user')->where('website_id', $websiteId)->count()],
            ['label' => 'website_plugin_installs', 'count' => DB::table('website_plugin_installs')->where('website_id', $websiteId)->count()],
            ['label' => 'redirect_suggestions', 'count' => DB::table('redirect_suggestions')->where('website_id', $websiteId)->count()],
            ['label' => 'outreach_prospects', 'count' => DB::table('outreach_prospects')->where('website_id', $websiteId)->count()],
            ['label' => 'client_activities', 'count' => DB::table('client_activities')->where('website_id', $websiteId)->count()],
        ];
    }
}

