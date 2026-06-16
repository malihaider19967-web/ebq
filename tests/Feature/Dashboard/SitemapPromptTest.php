<?php

namespace Tests\Feature\Dashboard;

use App\Jobs\CrawlWebsitePagesJob;
use App\Livewire\Dashboard\SitemapPrompt;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteSitemap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SitemapPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function act(Website $website): void
    {
        $this->actingAs(User::find($website->user_id));
        session(['current_website_id' => $website->id]);
    }

    public function test_shows_for_sourceless_site_with_no_sitemap(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withNoSources()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);
        $this->act($website);

        Livewire::test(SitemapPrompt::class)
            ->assertSee('Add a sitemap')
            ->assertSet('newSitemapUrl', '');
    }

    public function test_hidden_when_gsc_connected(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withGscOnly()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);
        $this->act($website);

        Livewire::test(SitemapPrompt::class)->assertDontSee('Add a sitemap');
    }

    public function test_hidden_when_a_sitemap_already_exists(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withNoSources()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);
        WebsiteSitemap::create(['website_id' => $website->id, 'path' => 'https://mysite.com/sitemap.xml', 'source' => 'manual']);
        $this->act($website);

        Livewire::test(SitemapPrompt::class)->assertDontSee('Add a sitemap');
    }

    public function test_add_sitemap_creates_row_and_queues_crawl(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withNoSources()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);
        $this->act($website);

        Livewire::test(SitemapPrompt::class)
            ->set('newSitemapUrl', 'https://mysite.com/sitemap.xml')
            ->call('addSitemap')
            ->assertSet('added', true)
            ->assertSee('crawling your pages');

        $this->assertDatabaseHas('website_sitemaps', [
            'website_id' => $website->id, 'path' => 'https://mysite.com/sitemap.xml', 'source' => 'manual',
        ]);
        Queue::assertPushed(CrawlWebsitePagesJob::class);
    }

    public function test_add_sitemap_rejects_invalid_url(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withNoSources()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);
        $this->act($website);

        Livewire::test(SitemapPrompt::class)
            ->set('newSitemapUrl', 'not-a-url')
            ->call('addSitemap')
            ->assertHasErrors('newSitemapUrl');

        Queue::assertNotPushed(CrawlWebsitePagesJob::class);
    }
}
