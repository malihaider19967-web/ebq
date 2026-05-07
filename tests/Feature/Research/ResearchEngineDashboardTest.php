<?php

namespace Tests\Feature\Research;

use App\Jobs\Research\RunCompetitorScanJob;
use App\Livewire\Admin\ResearchEngineDashboard;
use App\Models\Research\CompetitorScan;
use App\Models\Research\ResearchTarget;
use App\Models\User;
use App\Support\ResearchEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ResearchEngineDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.research.dashboard'))
            ->assertOk()
            ->assertSee('Research engine');
    }

    public function test_non_admin_cannot_open_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.research.dashboard'))
            ->assertForbidden();
    }

    public function test_run_now_dispatches_a_scan(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($admin);

        $target = ResearchTarget::create([
            'domain' => 'now.test',
            'root_url' => 'https://now.test/',
            'priority' => 50,
            'status' => ResearchTarget::STATUS_QUEUED,
        ]);

        Livewire::test(ResearchEngineDashboard::class)
            ->call('runNow', $target->id)
            ->assertSet('flash', 'Scan #'.CompetitorScan::query()->latest('id')->value('id').' dispatched for now.test.');

        Queue::assertPushed(RunCompetitorScanJob::class, 1);
        $this->assertSame(ResearchTarget::STATUS_SCANNING, $target->fresh()->status);
        $this->assertSame(1, CompetitorScan::query()->where('seed_domain', 'now.test')->count());
    }

    public function test_run_now_blocked_when_engine_paused(): void
    {
        Queue::fake();
        ResearchEngineSettings::save(['engine_paused' => true]);

        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($admin);

        $target = ResearchTarget::create([
            'domain' => 'paused.test',
            'priority' => 50,
            'status' => ResearchTarget::STATUS_QUEUED,
        ]);

        Livewire::test(ResearchEngineDashboard::class)
            ->call('runNow', $target->id)
            ->assertSet('flash', 'Engine is paused — unpause first.');

        Queue::assertNotPushed(RunCompetitorScanJob::class);
    }

    public function test_pause_and_resume_target(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($admin);

        $target = ResearchTarget::create([
            'domain' => 'flip.test',
            'priority' => 50,
            'status' => ResearchTarget::STATUS_QUEUED,
        ]);

        Livewire::test(ResearchEngineDashboard::class)
            ->call('pauseTarget', $target->id);
        $this->assertSame(ResearchTarget::STATUS_PAUSED, $target->fresh()->status);

        Livewire::test(ResearchEngineDashboard::class)
            ->call('resumeTarget', $target->id);
        $this->assertSame(ResearchTarget::STATUS_QUEUED, $target->fresh()->status);
    }

    public function test_delete_target_refuses_when_scanning(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($admin);

        $busy = ResearchTarget::create([
            'domain' => 'busy.test',
            'priority' => 50,
            'status' => ResearchTarget::STATUS_SCANNING,
        ]);

        Livewire::test(ResearchEngineDashboard::class)
            ->call('deleteTarget', $busy->id);

        $this->assertNotNull(ResearchTarget::query()->find($busy->id), 'Scanning targets must not be deletable.');
    }

    public function test_filters_narrow_the_queue(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($admin);

        ResearchTarget::create(['domain' => 'apple.test', 'priority' => 50, 'status' => ResearchTarget::STATUS_QUEUED, 'source' => 'manual']);
        ResearchTarget::create(['domain' => 'banana.test', 'priority' => 50, 'status' => ResearchTarget::STATUS_DONE, 'source' => 'outlink']);
        ResearchTarget::create(['domain' => 'cherry.test', 'priority' => 50, 'status' => ResearchTarget::STATUS_QUEUED, 'source' => 'outlink']);

        Livewire::test(ResearchEngineDashboard::class)
            ->set('statusFilter', 'queued')
            ->set('sourceFilter', 'outlink')
            ->assertViewHas('queue', function ($queue) {
                return $queue->count() === 1 && $queue->first()->domain === 'cherry.test';
            });
    }
}
