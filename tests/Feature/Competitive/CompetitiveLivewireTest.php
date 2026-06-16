<?php

namespace Tests\Feature\Competitive;

use App\Exceptions\QuotaExceededException;
use App\Livewire\Competitive\CompetitorDiscovery;
use App\Livewire\Competitive\KeywordGapAnalysis;
use App\Models\DiscoveredCompetitor;
use App\Models\KeywordGapAnalysis as GapAnalysis;
use App\Models\KeywordGapRow;
use App\Models\KeywordMetric;
use App\Models\User;
use App\Models\Website;
use App\Services\SerperSearchClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CompetitiveLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function actingWebsite(callable $factory = null): Website
    {
        $user = User::factory()->create();
        $website = ($factory ? $factory(Website::factory()) : Website::factory())
            ->create(['user_id' => $user->id, 'domain' => 'mysite.com']);
        $this->actingAs($user);
        session(['current_website_id' => $website->id]);

        return $website;
    }

    public function test_competitor_discovery_renders_and_validates_seeds_without_gsc(): void
    {
        $this->actingWebsite(fn ($f) => $f->withNoSources());

        Livewire::test(CompetitorDiscovery::class)
            ->assertOk()
            ->call('discover')
            ->assertSet('errorMessage', fn ($v) => is_string($v) && str_contains($v, 'seed keywords'));
    }

    public function test_keyword_gap_prefills_discovered_competitors_and_validates(): void
    {
        $website = $this->actingWebsite(fn ($f) => $f->withGscOnly());
        DiscoveredCompetitor::create([
            'website_id' => $website->id, 'competitor_domain' => 'rival.com',
            'appearances' => 5, 'keywords_sampled' => 10, 'score' => 80, 'run_id' => 'r1',
        ]);

        Livewire::test(KeywordGapAnalysis::class)
            ->assertOk()
            ->assertSet('competitors.0', 'rival.com')
            ->set('competitors', ['', '', ''])
            ->call('run')
            ->assertSet('errorMessage', fn ($v) => is_string($v) && str_contains($v, 'competitor'));
    }

    private function completedAnalysisWithRow(Website $website): KeywordGapRow
    {
        $analysis = GapAnalysis::create([
            'website_id' => $website->id, 'user_id' => $website->user_id, 'our_url' => 'mysite.com',
            'competitor_urls' => ['rival.com'], 'country' => 'us', 'status' => 'completed',
            'expires_at' => now()->addDays(30),
        ]);

        return KeywordGapRow::create([
            'keyword_gap_analysis_id' => $analysis->id, 'keyword' => 'best crm',
            'keyword_hash' => KeywordMetric::hashKeyword('best crm'), 'bucket' => 'missing',
            'search_volume' => 5000, 'opportunity_score' => 50,
        ]);
    }

    public function test_refine_surfaces_quota_message_and_upgrade_link(): void
    {
        Queue::fake();
        $website = $this->actingWebsite(fn ($f) => $f->withGscOnly());
        $row = $this->completedAnalysisWithRow($website);

        $serper = Mockery::mock(SerperSearchClient::class);
        $serper->shouldReceive('query')->andThrow(new QuotaExceededException('serp_api', 100, 100, 'You have hit your SERP limit', 'https://app/upgrade'));
        $this->app->instance(SerperSearchClient::class, $serper);

        Livewire::test(KeywordGapAnalysis::class)
            ->set('analysisId', $row->keyword_gap_analysis_id)
            ->call('computeLive', $row->id)
            ->assertSet('errorMessage', 'You have hit your SERP limit')
            ->assertSet('upgradeUrl', 'https://app/upgrade');

        $this->assertSame(50, $row->fresh()->opportunity_score); // unchanged
    }

    public function test_refine_success_marks_row_and_updates_score(): void
    {
        Queue::fake();
        $website = $this->actingWebsite(fn ($f) => $f->withGscOnly());
        $row = $this->completedAnalysisWithRow($website);

        $serper = Mockery::mock(SerperSearchClient::class);
        $serper->shouldReceive('query')->andReturn(['organic' => [['link' => 'https://rival.com/', 'position' => 1]]]);
        $this->app->instance(SerperSearchClient::class, $serper);

        Livewire::test(KeywordGapAnalysis::class)
            ->set('analysisId', $row->keyword_gap_analysis_id)
            ->call('computeLive', $row->id)
            ->assertSet('errorMessage', null)
            ->assertSet('refinedRows.'.$row->id, 'ok');

        $this->assertNotNull($row->fresh()->score_components);
    }
}
