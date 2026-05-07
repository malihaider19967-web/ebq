<?php

namespace Tests\Feature\Research;

use App\Jobs\Research\DiscoverEmergingNichesJob;
use App\Models\Research\Keyword;
use App\Models\Research\Niche;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use App\Models\User;
use App\Services\Research\ClusteringService;
use Database\Seeders\NicheTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DiscoverEmergingNichesTest extends TestCase
{
    use RefreshDatabase;

    private function seedKeywordWithSerp(string $query, array $domains): Keyword
    {
        $keyword = Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor($query), 'country' => 'us', 'language' => 'en'],
            ['query' => $query, 'normalized_query' => Keyword::normalize($query)]
        );

        $snap = SerpSnapshot::create([
            'keyword_id' => $keyword->id,
            'device' => 'desktop',
            'country' => 'us',
            'fetched_at' => Carbon::now(),
            'fetched_on' => Carbon::today(),
        ]);

        foreach ($domains as $i => $domain) {
            SerpResult::create([
                'snapshot_id' => $snap->id,
                'rank' => $i + 1,
                'url' => "https://{$domain}/x",
                'domain' => $domain,
                'result_type' => 'organic',
            ]);
        }

        return $keyword;
    }

    public function test_creates_dynamic_candidate_when_unmatched_cluster_meets_size_threshold(): void
    {
        (new NicheTaxonomySeeder())->run();

        $shared = ['niche-a.test', 'niche-b.test', 'niche-c.test', 'niche-d.test', 'niche-e.test',
                   'niche-f.test', 'niche-g.test', 'niche-h.test', 'niche-i.test', 'niche-j.test'];

        for ($i = 0; $i < 5; $i++) {
            $this->seedKeywordWithSerp("synthwave plugin variant {$i}", $shared);
        }

        (new DiscoverEmergingNichesJob(minClusterSize: 5))
            ->handle(app(ClusteringService::class));

        $candidate = Niche::query()
            ->where('is_dynamic', true)
            ->where('is_approved', false)
            ->first();
        $this->assertNotNull($candidate, 'Job should have created a candidate niche.');
        $this->assertStringStartsWith('dyn-', $candidate->slug);
    }

    public function test_skips_when_no_unmatched_keywords(): void
    {
        (new NicheTaxonomySeeder())->run();

        // Seed a curated niche match for the keyword so it is NOT unmatched.
        $running = Niche::query()->where('slug', 'running')->firstOrFail();
        $kw = $this->seedKeywordWithSerp('running shoes review', ['a.test', 'b.test', 'c.test', 'd.test', 'e.test']);
        $running->keywords()->attach($kw->id, ['relevance_score' => 0.9]);

        (new DiscoverEmergingNichesJob(minClusterSize: 1))
            ->handle(app(ClusteringService::class));

        $this->assertSame(0, Niche::query()->where('is_dynamic', true)->count());
    }

    public function test_admin_screen_lists_and_approves_candidates(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $candidate = Niche::create([
            'slug' => 'dyn-vintage-synth',
            'name' => 'Vintage Synth',
            'parent_id' => null,
            'is_dynamic' => true,
            'is_approved' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.research.niche-candidates.index'))
            ->assertOk()
            ->assertSee('dyn-vintage-synth');

        $parent = Niche::create([
            'slug' => 'music-test',
            'name' => 'Music',
            'parent_id' => null,
            'is_dynamic' => false,
            'is_approved' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.research.niche-candidates.approve', $candidate), [
                'parent_id' => $parent->id,
                'name' => 'Vintage Synthesizers',
            ])
            ->assertRedirect();

        $candidate->refresh();
        $this->assertTrue($candidate->is_approved);
        $this->assertSame($parent->id, $candidate->parent_id);
        $this->assertSame('Vintage Synthesizers', $candidate->name);
    }

    public function test_admin_can_reject_pending_candidate(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $candidate = Niche::create([
            'slug' => 'dyn-trashy',
            'name' => 'Trashy',
            'parent_id' => null,
            'is_dynamic' => true,
            'is_approved' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.research.niche-candidates.destroy', $candidate))
            ->assertRedirect();

        $this->assertNull(Niche::query()->find($candidate->id));
    }
}
