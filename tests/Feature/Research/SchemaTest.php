<?php

namespace Tests\Feature\Research;

use App\Models\Research\ContentBrief;
use App\Models\Research\Keyword;
use App\Models\Research\KeywordAlert;
use App\Models\Research\KeywordCluster;
use App\Models\Research\KeywordIntelligence;
use App\Models\Research\Niche;
use App\Models\Research\NicheAggregate;
use App\Models\Research\NicheTopicCluster;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use App\Models\Research\WebsiteInternalLink;
use App\Models\Research\WebsitePage;
use App\Models\Research\WebsitePageKeyword;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\NicheTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_phase_one_tables_exist(): void
    {
        $tables = [
            'keywords',
            'keyword_intelligence',
            'serp_snapshots',
            'serp_results',
            'serp_features',
            'keyword_clusters',
            'keyword_cluster_map',
            'niches',
            'niche_keyword_map',
            'niche_topic_clusters',
            'niche_aggregates',
            'website_niche_map',
            'website_pages',
            'website_page_keyword_map',
            'website_internal_links',
            'keyword_alerts',
            'content_briefs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumn('search_console_data', 'keyword_id'));
    }

    public function test_taxonomy_seeder_populates_hierarchical_niches(): void
    {
        (new NicheTaxonomySeeder())->run();

        $this->assertGreaterThanOrEqual(120, Niche::count());

        $topLevel = Niche::query()->whereNull('parent_id')->count();
        $this->assertSame(12, $topLevel, 'Expected 12 top-level verticals.');

        $children = Niche::query()->whereNotNull('parent_id')->count();
        $this->assertGreaterThan(0, $children);

        // Idempotent — re-running doesn't duplicate rows.
        $countBefore = Niche::count();
        (new NicheTaxonomySeeder())->run();
        $this->assertSame($countBefore, Niche::count());

        // No dynamic rows from a fresh seed.
        $this->assertSame(0, Niche::query()->where('is_dynamic', true)->count());
    }

    public function test_research_relations_round_trip(): void
    {
        (new NicheTaxonomySeeder())->run();

        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $keyword = Keyword::firstOrCreate(
            [
                'query_hash' => Keyword::hashFor('best running shoes'),
                'country' => 'us',
                'language' => 'en',
            ],
            [
                'query' => 'best running shoes',
                'normalized_query' => 'best running shoes',
            ]
        );

        KeywordIntelligence::create([
            'keyword_id' => $keyword->id,
            'search_volume' => 12000,
            'difficulty_score' => 64,
            'intent' => 'commercial',
        ]);

        $snapshot = SerpSnapshot::create([
            'keyword_id' => $keyword->id,
            'device' => 'desktop',
            'country' => 'us',
            'fetched_at' => Carbon::now(),
            'fetched_on' => Carbon::today(),
        ]);

        SerpResult::create([
            'snapshot_id' => $snapshot->id,
            'rank' => 1,
            'url' => 'https://example.com/best-running-shoes',
            'domain' => 'example.com',
            'title' => 'Best Running Shoes 2026',
        ]);

        $cluster = KeywordCluster::create([
            'cluster_name' => 'running shoes',
            'centroid_keyword_id' => $keyword->id,
        ]);
        $cluster->keywords()->attach($keyword->id, ['confidence' => 1.0]);

        $running = Niche::query()->where('slug', 'running')->firstOrFail();
        $running->keywords()->attach($keyword->id, ['relevance_score' => 0.92]);
        $website->niches()->attach($running->id, [
            'weight' => 0.7,
            'is_primary' => true,
            'source' => 'auto',
        ]);

        NicheTopicCluster::create([
            'niche_id' => $running->id,
            'cluster_id' => $cluster->id,
            'topic_name' => 'Running shoes',
            'total_search_volume' => 50000,
        ]);

        NicheAggregate::create([
            'niche_id' => $running->id,
            'keyword_id' => $keyword->id,
            'avg_ctr_by_position' => ['1' => 0.31, '2' => 0.18],
            'avg_content_length' => 2400,
            'sample_site_count' => 5,
        ]);

        $page = WebsitePage::firstOrCreate(
            [
                'website_id' => $website->id,
                'url_hash' => WebsitePage::hashUrl('https://site.test/running-shoes'),
            ],
            ['url' => 'https://site.test/running-shoes']
        );

        WebsitePageKeyword::create([
            'page_id' => $page->id,
            'keyword_id' => $keyword->id,
            'source' => 'gsc',
            'position_avg' => 8.4,
            'clicks_30d' => 120,
            'impressions_30d' => 4200,
        ]);

        $page2 = WebsitePage::firstOrCreate(
            [
                'website_id' => $website->id,
                'url_hash' => WebsitePage::hashUrl('https://site.test/trail-runners'),
            ],
            ['url' => 'https://site.test/trail-runners']
        );

        WebsiteInternalLink::create([
            'website_id' => $website->id,
            'from_page_id' => $page->id,
            'to_page_id' => $page2->id,
            'anchor_text' => 'trail runners',
            'status' => 'discovered',
        ]);

        KeywordAlert::create([
            'website_id' => $website->id,
            'keyword_id' => $keyword->id,
            'type' => KeywordAlert::TYPE_RANKING_DROP,
            'severity' => 'warn',
            'payload' => ['from' => 4, 'to' => 14],
        ]);

        ContentBrief::create([
            'website_id' => $website->id,
            'keyword_id' => $keyword->id,
            'created_by' => $user->id,
            'payload' => ['title' => 'Best Running Shoes', 'h2' => ['Cushioned', 'Trail']],
        ]);

        // Round-trip relationship checks.
        $this->assertSame(64, $keyword->fresh()->intelligence->difficulty_score);
        $this->assertCount(1, $keyword->fresh()->snapshots);
        $this->assertCount(1, $cluster->keywords);
        $this->assertSame('Running', $running->keywords()->first()->niches()->first()->name);
        $this->assertSame(1, $website->niches()->count());
        $this->assertTrue(NicheAggregate::aboveSamplingFloor()->exists());
        $this->assertSame(1, $page->outboundInternalLinks()->count());
        $this->assertSame(1, $page2->inboundInternalLinks()->count());
    }

    public function test_search_console_data_links_to_keyword(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $keyword = Keyword::firstOrCreate(
            [
                'query_hash' => Keyword::hashFor('matcha latte'),
                'country' => 'usa',
                'language' => 'en',
            ],
            [
                'query' => 'matcha latte',
                'normalized_query' => 'matcha latte',
            ]
        );

        SearchConsoleData::create([
            'website_id' => $website->id,
            'date' => Carbon::today()->subDay()->toDateString(),
            'query' => 'matcha latte',
            'page' => 'https://site.test/matcha',
            'clicks' => 5,
            'impressions' => 200,
            'position' => 9.2,
            'ctr' => 0.025,
            'country' => 'USA',
            'device' => '',
            'keyword_id' => $keyword->id,
        ]);

        $this->assertSame(1, SearchConsoleData::query()->where('keyword_id', $keyword->id)->count());
    }
}
