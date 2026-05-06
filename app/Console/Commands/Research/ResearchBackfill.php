<?php

namespace App\Console\Commands\Research;

use App\Models\Research\Keyword;
use App\Models\Research\WebsitePage;
use App\Models\SearchConsoleData;
use App\Models\Website;
use Database\Seeders\NicheTaxonomySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-1 backfill: brings the new Research data layer into a usable state
 * from existing GSC history.
 *
 *   1. Distinct (query, country) → upsert into `keywords`; back-reference
 *      keyword_id onto every matching search_console_data row.
 *   2. Distinct page → upsert into `website_pages` (lazy crawl deferred to
 *      Phase 2's CrawlWebsitePagesJob).
 *   3. Run NicheTaxonomySeeder if `niches` is empty.
 *
 * Idempotent — safe to re-run. Phase-2 will extend this command to
 * dispatch ClassifyWebsiteNichesJob per website once that job exists.
 */
class ResearchBackfill extends Command
{
    protected $signature = 'ebq:research-backfill
                            {--website= : Limit backfill to one website ID}
                            {--limit= : Cap distinct (query, country) tuples processed (debug)}
                            {--dry-run : Print the plan without writing}';

    protected $description = 'Populate Research keywords + website_pages from existing GSC data; seed the niche taxonomy.';

    public function handle(): int
    {
        $websiteOption = $this->option('website');
        $websiteId = null;
        if ($websiteOption !== null && $websiteOption !== '') {
            $websiteId = (int) $websiteOption;
            if ($websiteId <= 0 || ! Website::query()->whereKey($websiteId)->exists()) {
                $this->error("Website #{$websiteOption} not found.");

                return self::FAILURE;
            }
        }

        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->seedTaxonomy($dryRun);
        $this->backfillKeywords($websiteId, $limit, $dryRun);
        $this->backfillPages($websiteId, $limit, $dryRun);

        if ($dryRun) {
            $this->comment('Dry run — no rows written.');
        } else {
            $this->info('Research backfill complete.');
        }

        return self::SUCCESS;
    }

    private function seedTaxonomy(bool $dryRun): void
    {
        $existing = DB::table('niches')->count();
        if ($existing > 0) {
            $this->line("<fg=cyan>Niche taxonomy:</> {$existing} rows already present, skipping seeder.");

            return;
        }

        if ($dryRun) {
            $this->line('<fg=cyan>Niche taxonomy:</> would seed (table is empty).');

            return;
        }

        $this->line('<fg=cyan>Niche taxonomy:</> seeding…');
        (new NicheTaxonomySeeder())->run();
        $count = DB::table('niches')->count();
        $this->line("<fg=cyan>Niche taxonomy:</> seeded {$count} niches.");
    }

    private function backfillKeywords(?int $websiteId, ?int $limit, bool $dryRun): void
    {
        $query = SearchConsoleData::query()
            ->select('query', 'country')
            ->where('query', '!=', '')
            ->whereNull('keyword_id')
            ->when($websiteId, fn ($q) => $q->where('website_id', $websiteId))
            ->groupBy('query', 'country');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $tuples = $query->get();
        $count = $tuples->count();

        $this->line("<fg=cyan>Keywords:</> {$count} distinct (query, country) tuple(s) need a keyword_id.");

        if ($dryRun || $count === 0) {
            return;
        }

        $upserted = 0;
        $linked = 0;

        foreach ($tuples->chunk(500) as $chunk) {
            foreach ($chunk as $row) {
                $rawQuery = (string) $row->query;
                $country = $this->normalizeCountry((string) $row->country);
                $hash = Keyword::hashFor($rawQuery);

                $keyword = Keyword::firstOrCreate(
                    [
                        'query_hash' => $hash,
                        'country' => $country,
                        'language' => 'en',
                    ],
                    [
                        'query' => $rawQuery,
                        'normalized_query' => Keyword::normalize($rawQuery),
                    ]
                );

                if ($keyword->wasRecentlyCreated) {
                    $upserted++;
                }

                $linked += SearchConsoleData::query()
                    ->where('query', $rawQuery)
                    ->where('country', $row->country)
                    ->whereNull('keyword_id')
                    ->when($websiteId, fn ($q) => $q->where('website_id', $websiteId))
                    ->update(['keyword_id' => $keyword->id]);
            }
        }

        $this->line(sprintf('  · upserted %d new keyword row(s), linked %d GSC row(s).', $upserted, $linked));
    }

    private function backfillPages(?int $websiteId, ?int $limit, bool $dryRun): void
    {
        $query = SearchConsoleData::query()
            ->select('website_id', 'page')
            ->where('page', '!=', '')
            ->when($websiteId, fn ($q) => $q->where('website_id', $websiteId))
            ->groupBy('website_id', 'page');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $tuples = $query->get();
        $count = $tuples->count();

        $this->line("<fg=cyan>Pages:</> {$count} distinct (website_id, page) tuple(s) candidates.");

        if ($dryRun || $count === 0) {
            return;
        }

        $upserted = 0;

        foreach ($tuples->chunk(500) as $chunk) {
            foreach ($chunk as $row) {
                $url = (string) $row->page;
                $hash = WebsitePage::hashUrl($url);

                $page = WebsitePage::firstOrCreate(
                    [
                        'website_id' => (int) $row->website_id,
                        'url_hash' => $hash,
                    ],
                    ['url' => $url]
                );

                if ($page->wasRecentlyCreated) {
                    $upserted++;
                }
            }
        }

        $this->line("  · upserted {$upserted} new website_pages row(s).");
    }

    /**
     * GSC stores ISO-3166 alpha-3 codes ("USA"); the keywords table holds
     * lowercase alpha-2/3 freely. Normalise to lowercase, with empty
     * mapped to "global" so the unique key holds.
     */
    private function normalizeCountry(string $country): string
    {
        $c = mb_strtolower(trim($country));

        return $c === '' ? 'global' : $c;
    }
}
