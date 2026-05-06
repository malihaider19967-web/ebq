<?php

namespace Tests\Feature\Research;

use App\Models\Research\Keyword;
use App\Models\Research\Niche;
use App\Models\Research\NicheAggregate;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Services\Research\NicheAggregateRecomputeService;
use App\Services\Research\Privacy\PrivacyGuard;
use Database\Seeders\NicheTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Gating test for the cross-client aggregate path. The plan calls out the
 * n>=3 sample floor as the privacy invariant — these tests fail closed
 * if the recompute service ever writes sub-floor rows, or if a user can
 * read a website's research data they don't own.
 */
class PrivacyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function seedKeyword(string $query = 'matcha latte'): Keyword
    {
        return Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor($query), 'country' => 'us', 'language' => 'en'],
            ['query' => $query, 'normalized_query' => Keyword::normalize($query)]
        );
    }

    private function seedGsc(int $websiteId, int $keywordId, float $position = 5.0, float $ctr = 0.05, int $impressions = 100): void
    {
        SearchConsoleData::create([
            'website_id' => $websiteId,
            'date' => Carbon::today()->subDay()->toDateString(),
            'query' => 'matcha latte',
            'page' => "https://site{$websiteId}.test/matcha",
            'clicks' => max(1, (int) ($impressions * $ctr)),
            'impressions' => $impressions,
            'position' => $position,
            'ctr' => $ctr,
            'country' => 'USA',
            'device' => '',
            'keyword_id' => $keywordId,
        ]);
    }

    public function test_aggregate_recompute_refuses_sub_floor_rows(): void
    {
        (new NicheTaxonomySeeder())->run();

        $niche = Niche::query()->where('slug', 'recipes')->firstOrFail();
        $keyword = $this->seedKeyword();
        $niche->keywords()->attach($keyword->id, ['relevance_score' => 0.8]);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $w1 = Website::factory()->create(['user_id' => $u1->id]);
        $w2 = Website::factory()->create(['user_id' => $u2->id]);

        $this->seedGsc($w1->id, $keyword->id);
        $this->seedGsc($w2->id, $keyword->id);

        // Pre-seed a stale aggregate to verify the service deletes it
        // when sample falls below the floor.
        NicheAggregate::create([
            'niche_id' => $niche->id,
            'keyword_id' => $keyword->id,
            'avg_ctr_by_position' => ['1' => 0.3],
            'sample_site_count' => 1,
        ]);

        app(NicheAggregateRecomputeService::class)->recompute();

        $this->assertSame(0, NicheAggregate::query()
            ->where('niche_id', $niche->id)
            ->where('keyword_id', $keyword->id)
            ->count(), 'Aggregate row must NOT exist when only 2 websites contributed.');
    }

    public function test_aggregate_recompute_writes_when_floor_is_met(): void
    {
        (new NicheTaxonomySeeder())->run();
        $niche = Niche::query()->where('slug', 'recipes')->firstOrFail();
        $keyword = $this->seedKeyword();
        $niche->keywords()->attach($keyword->id, ['relevance_score' => 0.8]);

        for ($i = 1; $i <= 4; $i++) {
            $user = User::factory()->create();
            $website = Website::factory()->create(['user_id' => $user->id]);
            $this->seedGsc($website->id, $keyword->id, position: 4 + $i, ctr: 0.05 + 0.01 * $i);
        }

        app(NicheAggregateRecomputeService::class)->recompute();

        $row = NicheAggregate::query()
            ->where('niche_id', $niche->id)
            ->where('keyword_id', $keyword->id)
            ->first();

        $this->assertNotNull($row, 'Aggregate row must exist when 4 websites contributed.');
        $this->assertSame(4, (int) $row->sample_site_count);
        $this->assertGreaterThanOrEqual(PrivacyGuard::SAMPLE_FLOOR, (int) $row->sample_site_count);
        $this->assertIsArray($row->avg_ctr_by_position);
    }

    public function test_above_sampling_floor_scope_excludes_thin_rows(): void
    {
        (new NicheTaxonomySeeder())->run();
        $niche = Niche::query()->where('slug', 'recipes')->firstOrFail();
        $keyword = $this->seedKeyword();

        // Two rows: one safe, one thin. Only the safe one is exposed.
        NicheAggregate::create([
            'niche_id' => $niche->id,
            'keyword_id' => $keyword->id,
            'sample_site_count' => 8,
        ]);
        NicheAggregate::create([
            'niche_id' => $niche->id,
            'keyword_id' => null,
            'sample_site_count' => 2,
        ]);

        $exposed = NicheAggregate::aboveSamplingFloor()->get();
        $this->assertSame(1, $exposed->count());
        $this->assertSame($keyword->id, (int) $exposed->first()->keyword_id);
    }

    public function test_privacy_guard_blocks_unowned_website(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $owner->id]);

        $guard = new PrivacyGuard();

        $this->assertTrue($guard->canUserAccessWebsite($owner, $website->id));
        $this->assertFalse($guard->canUserAccessWebsite($stranger, $website->id));
        $this->assertFalse($guard->canUserAccessWebsite(null, $website->id));
    }
}
