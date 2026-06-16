<?php

namespace Tests\Feature;

use App\Jobs\CrawlWebsitePagesJob;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminClientCrawlTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_crawl_a_clients_single_website_without_picking(): void
    {
        Queue::fake();
        $client = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $client->id, 'domain' => 'one.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.clients.crawl', $client))
            ->assertRedirect();

        Queue::assertPushed(CrawlWebsitePagesJob::class, fn ($j) => $j->websiteId === $website->id && $j->force === true);
    }

    public function test_admin_must_pick_a_website_when_client_has_several(): void
    {
        Queue::fake();
        // Agency plan (50 sites) so neither site is frozen by the plan limit —
        // this test is about the picker, not freezing.
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $client = User::factory()->create(['current_plan_slug' => 'agency']);
        Website::factory()->create(['user_id' => $client->id, 'domain' => 'a.com']);
        Website::factory()->create(['user_id' => $client->id, 'domain' => 'b.com']);

        // No website_id -> nothing dispatched.
        $this->actingAs($this->admin())
            ->post(route('admin.clients.crawl', $client))
            ->assertRedirect();
        Queue::assertNotPushed(CrawlWebsitePagesJob::class);

        // With website_id -> that site is crawled.
        $target = Website::where('domain', 'b.com')->first();
        $this->actingAs($this->admin())
            ->post(route('admin.clients.crawl', $client), ['website_id' => $target->id])
            ->assertRedirect();
        Queue::assertPushed(CrawlWebsitePagesJob::class, fn ($j) => $j->websiteId === $target->id);
    }

    public function test_admin_crawl_is_blocked_for_a_site_frozen_by_plan_limit(): void
    {
        Queue::fake();
        config(['app.free' => false]); // don't let the all-Pro promo lift the limit
        $this->seed(\Database\Seeders\PlanSeeder::class);

        // Free plan = 1 website; the client owns 2, so the newer one is frozen.
        $client = User::factory()->create(['current_plan_slug' => 'free']);
        $active = Website::factory()->create(['user_id' => $client->id, 'domain' => 'old.com', 'created_at' => now()->subDays(2)]);
        $frozen = Website::factory()->create(['user_id' => $client->id, 'domain' => 'new.com', 'created_at' => now()]);

        $this->assertSame([$frozen->id], $client->fresh()->frozenWebsiteIds());

        // Crawling the frozen site dispatches nothing and explains why.
        $this->actingAs($this->admin())
            ->post(route('admin.clients.crawl', $client), ['website_id' => $frozen->id])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($s) => str_contains((string) $s, 'frozen'));
        Queue::assertNotPushed(CrawlWebsitePagesJob::class);

        // The active (oldest) site still crawls normally.
        $this->actingAs($this->admin())
            ->post(route('admin.clients.crawl', $client), ['website_id' => $active->id])
            ->assertRedirect();
        Queue::assertPushed(CrawlWebsitePagesJob::class, fn ($j) => $j->websiteId === $active->id);
    }

    public function test_non_admin_cannot_trigger_crawl(): void
    {
        Queue::fake();
        $client = User::factory()->create();
        Website::factory()->create(['user_id' => $client->id]);

        $this->actingAs(User::factory()->create()) // not an admin
            ->post(route('admin.clients.crawl', $client))
            ->assertForbidden();

        Queue::assertNotPushed(CrawlWebsitePagesJob::class);
    }
}
