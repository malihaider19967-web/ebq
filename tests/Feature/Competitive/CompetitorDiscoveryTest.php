<?php

namespace Tests\Feature\Competitive;

use App\Models\CompetitorDiscoveryRun;
use App\Models\DiscoveredCompetitor;
use App\Models\User;
use App\Models\Website;
use App\Services\Competitive\CompetitorDiscoveryService;
use App\Services\SerperSearchClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CompetitorDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // isolate the DA-enrichment side dispatch
        config(['services.competitive.discovery_refresh_days' => 14]);
    }

    private function website(string $domain = 'mysite.com'): Website
    {
        $user = User::factory()->create();

        return Website::factory()->create(['user_id' => $user->id, 'domain' => $domain]);
    }

    /**
     * @param  array<string, list<array{domain: string, position: int}>>  $serpByKeyword
     */
    private function fakeSerper(array $serpByKeyword): void
    {
        $serper = Mockery::mock(SerperSearchClient::class);
        $serper->shouldReceive('query')->andReturnUsing(function (array $params) use ($serpByKeyword): array {
            $q = (string) ($params['q'] ?? '');
            $organic = [];
            foreach ($serpByKeyword[$q] ?? [] as $r) {
                $organic[] = ['link' => 'https://www.'.$r['domain'].'/', 'position' => $r['position'], 'title' => 'x'];
            }

            return ['organic' => $organic];
        });
        $this->app->instance(SerperSearchClient::class, $serper);
    }

    public function test_tally_scores_and_excludes_giants_and_own_domain(): void
    {
        $website = $this->website('mysite.com');
        $this->fakeSerper([
            'alpha' => [
                ['domain' => 'mysite.com', 'position' => 1],     // own — excluded
                ['domain' => 'wikipedia.org', 'position' => 2],  // giant — excluded
                ['domain' => 'rival.com', 'position' => 3],
                ['domain' => 'other.com', 'position' => 5],
            ],
            'beta' => [
                ['domain' => 'rival.com', 'position' => 1],
            ],
        ]);

        $run = CompetitorDiscoveryRun::create([
            'run_id' => 'run-1', 'website_id' => $website->id, 'status' => 'queued',
            'keywords_planned' => 2, 'seed_source' => 'manual',
        ]);

        app(CompetitorDiscoveryService::class)->run('run-1', ['alpha', 'beta']);

        $this->assertDatabaseMissing('discovered_competitors', ['competitor_domain' => 'mysite.com']);
        $this->assertDatabaseMissing('discovered_competitors', ['competitor_domain' => 'wikipedia.org']);

        $rival = DiscoveredCompetitor::where('website_id', $website->id)->where('competitor_domain', 'rival.com')->first();
        $this->assertNotNull($rival);
        $this->assertSame(2, $rival->appearances);
        $this->assertSame(2, $rival->keywords_sampled);
        $this->assertEqualsWithDelta(2.0, $rival->avg_position, 0.01); // (3 + 1) / 2
        $this->assertSame(1, $rival->best_position);

        // rival (2 appearances, avg pos 2) outranks other (1 appearance, pos 5).
        $other = DiscoveredCompetitor::where('competitor_domain', 'other.com')->first();
        $this->assertGreaterThan($other->score, $rival->score);

        $run->refresh();
        $this->assertSame('completed', $run->status);
    }

    public function test_refresh_cadence_gate_skips_when_fresh(): void
    {
        $website = $this->website();
        CompetitorDiscoveryRun::create([
            'run_id' => 'fresh', 'website_id' => $website->id, 'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);

        $result = app(CompetitorDiscoveryService::class)->queueRunIfStale($website, $website->user_id, ['seed']);

        $this->assertNull($result);
        Queue::assertNotPushed(\App\Jobs\RunCompetitorDiscovery::class);
    }

    public function test_prune_removes_rows_from_older_runs(): void
    {
        $website = $this->website('mysite.com');
        DiscoveredCompetitor::create([
            'website_id' => $website->id, 'competitor_domain' => 'stale.com',
            'appearances' => 1, 'keywords_sampled' => 1, 'score' => 50, 'run_id' => 'old-run',
        ]);

        $this->fakeSerper(['alpha' => [['domain' => 'rival.com', 'position' => 1]]]);
        CompetitorDiscoveryRun::create(['run_id' => 'new-run', 'website_id' => $website->id, 'status' => 'queued']);

        app(CompetitorDiscoveryService::class)->run('new-run', ['alpha']);

        $this->assertDatabaseMissing('discovered_competitors', ['competitor_domain' => 'stale.com']);
        $this->assertDatabaseHas('discovered_competitors', ['competitor_domain' => 'rival.com', 'run_id' => 'new-run']);
    }

    public function test_serp_call_cap_is_enforced(): void
    {
        config(['services.competitive.discovery_max_keywords' => 2]);
        $website = $this->website();

        $serper = Mockery::mock(SerperSearchClient::class);
        $serper->shouldReceive('query')->times(2)->andReturn(['organic' => [['link' => 'https://rival.com/', 'position' => 1]]]);
        $this->app->instance(SerperSearchClient::class, $serper);

        CompetitorDiscoveryRun::create(['run_id' => 'capped', 'website_id' => $website->id, 'status' => 'queued']);
        app(CompetitorDiscoveryService::class)->run('capped', ['k1', 'k2', 'k3', 'k4', 'k5']);

        $run = CompetitorDiscoveryRun::where('run_id', 'capped')->first();
        $this->assertSame(2, $run->serp_calls_made);
    }
}
