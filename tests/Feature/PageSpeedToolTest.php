<?php

namespace Tests\Feature;

use App\Jobs\RunPageSpeedStrategy;
use App\Livewire\Pages\PageSpeed;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PageSpeedToolTest extends TestCase
{
    use RefreshDatabase;

    private function configureService(): void
    {
        config(['services.lighthouse.url' => 'http://lh.test', 'services.lighthouse.key' => 'k']);
    }

    /** A parsed single-strategy report, as RunPageSpeedStrategy would cache. */
    private function fakeStrategy(int $perf): array
    {
        return [
            'strategy' => 'mobile',
            'lighthouse_version' => '12.3.0',
            'scores' => ['performance' => $perf, 'accessibility' => 95, 'best_practices' => 92, 'seo' => 90],
            'metrics' => [
                ['key' => 'lcp', 'label' => 'Largest Contentful Paint', 'display' => '2.1 s', 'rating' => 'good'],
            ],
            'opportunities' => [
                ['id' => 'render-blocking-resources', 'title' => 'Eliminate render-blocking resources', 'savings_ms' => 500, 'display' => null, 'description' => 'Help.', 'rating' => 'poor'],
            ],
            'diagnostics' => [],
            'failed_audits' => ['accessibility' => [], 'best_practices' => [], 'seo' => []],
            'screenshot' => null,
        ];
    }

    public function test_pagespeed_page_loads_for_user_with_audit_access(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->withSession(['current_website_id' => $website->id])
            ->get(route('pagespeed.index'))
            ->assertOk()
            ->assertSee('PageSpeed Insights', false);
    }

    public function test_run_test_validates_the_url(): void
    {
        $this->configureService();
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(PageSpeed::class)
            ->set('url', '')
            ->call('runTest')
            ->assertHasErrors('url');

        Livewire::test(PageSpeed::class)
            ->set('url', 'not a url')
            ->call('runTest')
            ->assertHasErrors('url');
    }

    public function test_run_test_dispatches_one_job_per_strategy_and_enters_running(): void
    {
        Queue::fake();
        $this->configureService();
        $this->actingAs(User::factory()->create());

        Livewire::test(PageSpeed::class)
            ->set('url', 'example.com/page')
            ->call('runTest')
            ->assertHasNoErrors()
            ->assertSet('status', 'running')
            ->assertSet('testedUrl', 'https://example.com/page');

        Queue::assertPushed(RunPageSpeedStrategy::class, 2);
        Queue::assertPushed(fn (RunPageSpeedStrategy $j) => $j->strategy === 'mobile');
        Queue::assertPushed(fn (RunPageSpeedStrategy $j) => $j->strategy === 'desktop');
    }

    public function test_poll_assembles_full_report_once_both_strategies_finish(): void
    {
        Queue::fake();
        $this->configureService();
        $this->actingAs(User::factory()->create());

        $test = Livewire::test(PageSpeed::class)
            ->set('url', 'example.com/page')
            ->call('runTest')
            ->assertSet('status', 'running');

        $runId = $test->get('runId');
        Cache::put(RunPageSpeedStrategy::keyFor($runId, 'mobile'), $this->fakeStrategy(84));
        Cache::put(RunPageSpeedStrategy::keyFor($runId, 'desktop'), $this->fakeStrategy(97));

        $test->call('pollResult')
            ->assertSet('status', 'done')
            ->assertSee('Performance', false)
            ->assertSee('Accessibility', false)
            ->assertSee('Largest Contentful Paint', false)
            ->assertSee('Eliminate render-blocking resources', false)
            ->assertSee('Mobile', false)
            ->assertSee('Desktop', false);
    }

    public function test_poll_keeps_waiting_until_both_strategies_arrive(): void
    {
        Queue::fake();
        $this->configureService();
        $this->actingAs(User::factory()->create());

        $test = Livewire::test(PageSpeed::class)->set('url', 'example.com')->call('runTest');
        $runId = $test->get('runId');

        // Only mobile is in — still running.
        Cache::put(RunPageSpeedStrategy::keyFor($runId, 'mobile'), $this->fakeStrategy(84));

        $test->call('pollResult')->assertSet('status', 'running');
    }

    public function test_poll_reports_failure_when_both_strategies_fail(): void
    {
        Queue::fake();
        $this->configureService();
        $this->actingAs(User::factory()->create());

        $test = Livewire::test(PageSpeed::class)->set('url', 'https://example.com')->call('runTest');
        $runId = $test->get('runId');

        Cache::put(RunPageSpeedStrategy::keyFor($runId, 'mobile'), ['error' => true]);
        Cache::put(RunPageSpeedStrategy::keyFor($runId, 'desktop'), ['error' => true]);

        $test->call('pollResult')
            ->assertSet('status', 'done')
            ->assertSet('result', null)
            ->assertSee('Could not measure that URL', false);
    }
}
