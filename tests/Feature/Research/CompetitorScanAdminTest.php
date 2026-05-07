<?php

namespace Tests\Feature\Research;

use App\Jobs\Research\RunCompetitorScanJob;
use App\Models\Research\CompetitorScan;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompetitorScanAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    }

    public function test_admin_can_open_index_create_and_show(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.research.competitor-scans.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.research.competitor-scans.create'))->assertOk();

        $scan = CompetitorScan::create([
            'triggered_by_user_id' => $admin->id,
            'seed_domain' => 'example.com',
            'seed_url' => 'https://example.com',
            'seed_keywords' => ['example domain'],
            'caps' => ['max_total_pages' => 50, 'max_pages_per_external_domain' => 5, 'max_depth' => 3],
            'status' => CompetitorScan::STATUS_QUEUED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.research.competitor-scans.show', $scan))
            ->assertOk()
            ->assertSee('example.com');
    }

    public function test_store_dispatches_job_and_normalises_inputs(): void
    {
        Queue::fake();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.research.competitor-scans.store'), [
                'seed_url' => 'https://www.competitor.com/path',
                'seed_keywords' => "  best running shoes \n trail running gear \n best running shoes \n",
                'max_total_pages' => 100,
                'max_pages_per_external_domain' => 5,
                'max_depth' => 3,
            ])
            ->assertRedirect();

        $scan = CompetitorScan::query()->latest('id')->firstOrFail();

        $this->assertSame('competitor.com', $scan->seed_domain);
        $this->assertSame(['best running shoes', 'trail running gear'], $scan->seed_keywords);
        $this->assertSame(100, $scan->caps['max_total_pages']);
        $this->assertSame(CompetitorScan::STATUS_QUEUED, $scan->status);

        Queue::assertPushed(RunCompetitorScanJob::class, fn ($job) => $job->scanId === $scan->id);
    }

    public function test_store_blocks_a_concurrent_scan_for_the_same_domain(): void
    {
        $admin = $this->admin();

        CompetitorScan::create([
            'triggered_by_user_id' => $admin->id,
            'seed_domain' => 'competitor.com',
            'seed_url' => 'https://competitor.com',
            'caps' => ['max_total_pages' => 100, 'max_pages_per_external_domain' => 5, 'max_depth' => 3],
            'status' => CompetitorScan::STATUS_RUNNING,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.research.competitor-scans.store'), [
                'seed_url' => 'https://www.competitor.com/start',
                'max_total_pages' => 50,
                'max_pages_per_external_domain' => 5,
                'max_depth' => 3,
            ])
            ->assertSessionHasErrors('seed_url');

        $this->assertSame(1, CompetitorScan::query()->where('seed_domain', 'competitor.com')->count());
    }

    public function test_store_caps_input_at_server_ceiling(): void
    {
        config()->set('research.scraper.ceiling_total_pages', 200);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.research.competitor-scans.store'), [
                'seed_url' => 'https://huge.test',
                'max_total_pages' => 9999,
                'max_pages_per_external_domain' => 5,
                'max_depth' => 3,
            ])
            ->assertSessionHasErrors('max_total_pages');
    }

    public function test_cancel_flips_status_when_scan_is_active(): void
    {
        $admin = $this->admin();
        $scan = CompetitorScan::create([
            'triggered_by_user_id' => $admin->id,
            'seed_domain' => 'mid-flight.test',
            'seed_url' => 'https://mid-flight.test',
            'caps' => ['max_total_pages' => 100, 'max_pages_per_external_domain' => 5, 'max_depth' => 3],
            'status' => CompetitorScan::STATUS_RUNNING,
            'started_at' => now()->subMinute(),
            'last_heartbeat_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.research.competitor-scans.cancel', $scan))
            ->assertRedirect();

        $this->assertSame(CompetitorScan::STATUS_CANCELLING, $scan->fresh()->status);
        $this->assertNotNull($scan->fresh()->cancelled_at);
    }

    public function test_mark_failed_only_works_when_heartbeat_is_stale(): void
    {
        $admin = $this->admin();
        $fresh = CompetitorScan::create([
            'triggered_by_user_id' => $admin->id,
            'seed_domain' => 'fresh.test',
            'seed_url' => 'https://fresh.test',
            'caps' => ['max_total_pages' => 100, 'max_pages_per_external_domain' => 5, 'max_depth' => 3],
            'status' => CompetitorScan::STATUS_RUNNING,
            'last_heartbeat_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.research.competitor-scans.mark-failed', $fresh))
            ->assertSessionHasErrors('status');
        $this->assertSame(CompetitorScan::STATUS_RUNNING, $fresh->fresh()->status);

        $stale = CompetitorScan::create([
            'triggered_by_user_id' => $admin->id,
            'seed_domain' => 'stale.test',
            'seed_url' => 'https://stale.test',
            'caps' => ['max_total_pages' => 100, 'max_pages_per_external_domain' => 5, 'max_depth' => 3],
            'status' => CompetitorScan::STATUS_RUNNING,
            'last_heartbeat_at' => Carbon::now()->subMinutes(5),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.research.competitor-scans.mark-failed', $stale))
            ->assertRedirect();
        $this->assertSame(CompetitorScan::STATUS_FAILED, $stale->fresh()->status);
    }

    public function test_non_admin_cannot_access_competitor_scans(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.research.competitor-scans.index'))
            ->assertForbidden();
    }

    public function test_competitor_scan_relations_round_trip(): void
    {
        $admin = $this->admin();
        $website = Website::factory()->create(['user_id' => $admin->id]);

        $scan = CompetitorScan::create([
            'website_id' => $website->id,
            'triggered_by_user_id' => $admin->id,
            'seed_domain' => 'rels.test',
            'seed_url' => 'https://rels.test',
            'caps' => ['max_total_pages' => 50, 'max_pages_per_external_domain' => 5, 'max_depth' => 3],
            'status' => CompetitorScan::STATUS_DONE,
            'page_count' => 2,
        ]);

        $page1 = $scan->pages()->create([
            'url' => 'https://rels.test/a',
            'url_hash' => hash('sha256', 'https://rels.test/a'),
            'domain' => 'rels.test',
            'title' => 'A',
            'word_count' => 100,
        ]);
        $page2 = $scan->pages()->create([
            'url' => 'https://rels.test/b',
            'url_hash' => hash('sha256', 'https://rels.test/b'),
            'domain' => 'rels.test',
            'title' => 'B',
            'word_count' => 200,
        ]);

        $scan->outlinks()->create([
            'from_page_id' => $page1->id,
            'to_url' => 'https://rels.test/b',
            'to_url_hash' => hash('sha256', 'https://rels.test/b'),
            'to_domain' => 'rels.test',
            'is_external' => false,
        ]);

        $topic = $scan->topics()->create(['name' => 'Topic A', 'page_count' => 2]);
        $topic->pages()->attach([$page1->id, $page2->id]);

        $this->assertSame(2, $scan->pages()->count());
        $this->assertSame(1, $scan->outlinks()->count());
        $this->assertSame(2, $topic->fresh()->pages()->count());
        $this->assertTrue($scan->isActive() === false);
    }
}
