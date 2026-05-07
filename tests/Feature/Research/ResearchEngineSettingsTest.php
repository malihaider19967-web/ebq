<?php

namespace Tests\Feature\Research;

use App\Console\Commands\Research\BootstrapResearchWebsites;
use App\Console\Commands\Research\ScanNextResearchTarget;
use App\Jobs\Research\AutoEnqueueOutlinksJob;
use App\Jobs\Research\DiscoverCompetitorsForWebsiteJob;
use App\Jobs\Research\RunCompetitorScanJob;
use App\Models\Research\CompetitorScan;
use App\Models\Research\ResearchTarget;
use App\Models\User;
use App\Models\Website;
use App\Services\Research\CompetitorDiscoveryService;
use App\Support\ResearchEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResearchEngineSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_load_settings_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.research.settings.show'))
            ->assertOk()
            ->assertSee('Research engine settings');
    }

    public function test_non_admin_cannot_load_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.research.settings.show'))
            ->assertForbidden();
    }

    public function test_settings_persist_round_trip(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->put(route('admin.research.settings.update'), [
                'engine_paused' => '1',
                'auto_discovery_disabled' => '1',
                'auto_fetch_volume' => '1',
                'embeddings_enabled' => '0',
                'rollout_mode' => 'cohort',
                'rollout_allowlist' => '1, 2 3',
                'quotas' => [
                    'keyword_lookup' => 7777,
                    'serp_fetch' => 8888,
                    'llm_call' => 9999,
                    'brief' => 11,
                ],
                'scraper' => [
                    'ceiling_total_pages' => 1234,
                    'ceiling_external_per_domain' => 7,
                    'ceiling_depth' => 5,
                    'timeout_seconds' => 1800,
                ],
            ])
            ->assertRedirect();

        $this->assertTrue(ResearchEngineSettings::enginePaused());
        $this->assertTrue(ResearchEngineSettings::autoDiscoveryDisabled());
        $this->assertTrue(ResearchEngineSettings::autoFetchVolume());
        $this->assertSame('cohort', ResearchEngineSettings::rolloutMode());
        $this->assertSame([1, 2, 3], ResearchEngineSettings::rolloutAllowlist());
        $this->assertSame(7777, ResearchEngineSettings::quota('keyword_lookup'));
        $this->assertSame(1234, ResearchEngineSettings::scraper()['ceiling_total_pages']);
        $this->assertSame(1800, ResearchEngineSettings::scraper()['timeout_seconds']);
    }

    public function test_engine_paused_no_ops_scheduler(): void
    {
        Queue::fake();
        ResearchEngineSettings::save(['engine_paused' => true]);

        $target = ResearchTarget::create([
            'domain' => 'example.com',
            'priority' => 50,
            'status' => ResearchTarget::STATUS_QUEUED,
        ]);

        $this->artisan('ebq:research-scan-next')
            ->assertSuccessful()
            ->expectsOutputToContain('paused');

        Queue::assertNotPushed(RunCompetitorScanJob::class);
        $this->assertSame(ResearchTarget::STATUS_QUEUED, $target->fresh()->status);
        $this->assertSame(0, CompetitorScan::query()->count());
    }

    public function test_engine_running_dispatches_scheduler(): void
    {
        Queue::fake();
        ResearchEngineSettings::save(['engine_paused' => false]);

        ResearchTarget::create([
            'domain' => 'example.com',
            'root_url' => 'https://example.com/',
            'priority' => 50,
            'status' => ResearchTarget::STATUS_QUEUED,
        ]);

        $this->artisan('ebq:research-scan-next')
            ->assertSuccessful();

        Queue::assertPushed(RunCompetitorScanJob::class, 1);
        $this->assertSame(1, CompetitorScan::query()->count());
    }

    public function test_auto_discovery_disabled_skips_competitor_discovery_job(): void
    {
        ResearchEngineSettings::save([
            'engine_paused' => false,
            'auto_discovery_disabled' => true,
        ]);

        // CompetitorDiscoveryService MUST NOT be called when auto-discovery is disabled.
        $this->mock(CompetitorDiscoveryService::class, function ($mock) {
            $mock->shouldNotReceive('discoverForWebsite');
        });

        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        (new DiscoverCompetitorsForWebsiteJob($website->id))->handle(
            app(CompetitorDiscoveryService::class)
        );

        // No targets created from the (skipped) discovery.
        $this->assertSame(0, ResearchTarget::query()
            ->where('source', ResearchTarget::SOURCE_SERP_COMPETITOR)
            ->count());
    }

    public function test_engine_paused_skips_outlink_auto_enqueue(): void
    {
        ResearchEngineSettings::save(['engine_paused' => true]);

        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $scan = CompetitorScan::create([
            'website_id' => $website->id,
            'seed_domain' => 'paused.test',
            'seed_url' => 'https://paused.test',
            'caps' => ['max_total_pages' => 50, 'max_pages_per_external_domain' => 5, 'max_depth' => 3],
            'status' => CompetitorScan::STATUS_DONE,
        ]);
        $scan->pages()->create([
            'url' => 'https://paused.test/a',
            'url_hash' => hash('sha256', 'https://paused.test/a'),
            'domain' => 'paused.test',
            'word_count' => 1,
        ]);
        $scan->outlinks()->create([
            'from_page_id' => $scan->pages()->first()->id,
            'to_url' => 'https://other.test/x',
            'to_url_hash' => hash('sha256', 'https://other.test/x'),
            'to_domain' => 'other.test',
            'is_external' => true,
        ]);

        (new AutoEnqueueOutlinksJob($scan->id))->handle();

        $this->assertSame(0, ResearchTarget::query()->where('domain', 'other.test')->count());
    }

    public function test_engine_paused_no_ops_bootstrap_websites(): void
    {
        ResearchEngineSettings::save(['engine_paused' => true]);

        $this->artisan('ebq:research-bootstrap-websites')
            ->assertSuccessful()
            ->expectsOutputToContain('paused');
    }
}
