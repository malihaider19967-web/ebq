<?php

namespace Tests\Feature;

use App\Livewire\Sitemaps\SitemapsManager;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteSitemap;
use App\Services\Google\SearchConsoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class SitemapsManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_client_can_add_a_sitemap_manually(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        session(['current_website_id' => $website->id]);

        Livewire::actingAs($user)
            ->test(SitemapsManager::class)
            ->set('newSitemapUrl', 'https://example.com/sitemap.xml')
            ->call('addSitemap')
            ->assertSet('newSitemapUrl', '')
            ->assertSee('https://example.com/sitemap.xml');

        $this->assertDatabaseHas('website_sitemaps', [
            'website_id' => $website->id,
            'path' => 'https://example.com/sitemap.xml',
            'source' => WebsiteSitemap::SOURCE_MANUAL,
        ]);
    }

    public function test_invalid_url_is_rejected(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        session(['current_website_id' => $website->id]);

        Livewire::actingAs($user)
            ->test(SitemapsManager::class)
            ->set('newSitemapUrl', 'not-a-url')
            ->call('addSitemap')
            ->assertHasErrors(['newSitemapUrl']);

        $this->assertDatabaseCount('website_sitemaps', 0);
    }

    public function test_client_can_remove_a_sitemap(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $sitemap = WebsiteSitemap::create([
            'website_id' => $website->id,
            'path' => 'https://example.com/sitemap.xml',
            'source' => WebsiteSitemap::SOURCE_MANUAL,
        ]);
        session(['current_website_id' => $website->id]);

        Livewire::actingAs($user)
            ->test(SitemapsManager::class)
            ->call('removeSitemap', $sitemap->id);

        $this->assertDatabaseMissing('website_sitemaps', ['id' => $sitemap->id]);
    }

    public function test_sync_from_gsc_stores_sitemaps(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withBothSources()->create(['user_id' => $user->id]);
        session(['current_website_id' => $website->id]);

        $mock = Mockery::mock(SearchConsoleService::class);
        $mock->shouldReceive('listSitemaps')->once()->andReturn([
            [
                'path' => 'https://example.com/sitemap.xml',
                'type' => 'WEB',
                'is_pending' => false,
                'is_sitemaps_index' => false,
                'errors' => 0,
                'warnings' => 2,
                'submitted_urls' => 120,
                'indexed_urls' => 100,
                'last_submitted' => '2026-06-01T00:00:00.000Z',
                'last_downloaded' => '2026-06-02T00:00:00.000Z',
            ],
        ]);
        $this->app->instance(SearchConsoleService::class, $mock);

        Livewire::actingAs($user)
            ->test(SitemapsManager::class)
            ->call('syncFromGsc')
            ->assertSee('Synced sitemaps');

        $this->assertDatabaseHas('website_sitemaps', [
            'website_id' => $website->id,
            'path' => 'https://example.com/sitemap.xml',
            'source' => WebsiteSitemap::SOURCE_GSC,
            'submitted_urls' => 120,
            'indexed_urls' => 100,
            'warnings' => 2,
        ]);
    }

    public function test_a_user_cannot_touch_another_users_website(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherWebsite = Website::factory()->create(['user_id' => $other->id]);
        session(['current_website_id' => $otherWebsite->id]);

        Livewire::actingAs($user)
            ->test(SitemapsManager::class)
            ->set('newSitemapUrl', 'https://evil.com/sitemap.xml')
            ->call('addSitemap');

        $this->assertDatabaseCount('website_sitemaps', 0);
    }
}
