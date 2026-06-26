<?php

namespace Tests\Feature;

use App\Models\CrawlFinding;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Services\Crawler\CrawlReportService;
use App\Support\Crawler\FrontierUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlParamAndExternalTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontier_collapses_noise_params_but_keeps_pagination(): void
    {
        $this->assertSame('https://x.com/id', FrontierUrl::collapse('https://x.com/id?name=abc'));
        $this->assertSame('https://x.com/id', FrontierUrl::collapse('https://x.com/id?nick=max&utm_source=fb'));
        $this->assertSame('https://x.com/list?page=2', FrontierUrl::collapse('https://x.com/list?page=2'));
        $this->assertSame('https://x.com/id?page=2', FrontierUrl::collapse('https://x.com/id?name=a&page=2'));
        $this->assertSame('https://x.com/clean', FrontierUrl::collapse('https://x.com/clean'));
    }

    public function test_broken_external_row_shows_source_page_and_fixes_via_its_page_health(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $source = WebsitePage::create(['crawl_site_id' => $website->crawl_site_id, 'url' => 'https://example.com/blog/post', 'url_hash' => WebsitePage::hashUrl('https://example.com/blog/post'), 'http_status' => 200, 'is_indexable' => true, 'last_crawled_at' => now()]);

        CrawlFinding::create([
            'crawl_site_id' => $website->crawl_site_id, 'page_id' => $source->id, 'category' => 'broken_link', 'type' => 'broken_external',
            'severity' => 'medium', 'impact' => 0, 'affected_url' => 'https://dead.example.org/x',
            'affected_url_hash' => CrawlFinding::hashUrl('https://dead.example.org/x'),
            'detail' => ['http_status' => 404], 'status' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $rows = app(CrawlReportService::class)->issueRows('broken_link', $website->id);
        $ext = collect($rows)->firstWhere('title', 'https://dead.example.org/x');

        $this->assertNotNull($ext);
        $this->assertStringContainsString('/blog/post', $ext['subtitle']); // shows WHERE it is
        // Fix routes to OUR Page Health view for the source page (not the live
        // site) — same destination every other finding type uses, carrying which
        // issue sent the user here so it's not a context-free landing.
        $this->assertStringContainsString('link-structure', (string) $ext['fix_url']);
        $this->assertStringContainsString('blog%2Fpost', (string) $ext['fix_url']);
        $this->assertStringContainsString('issue=broken_external', (string) $ext['fix_url']);
        $this->assertArrayNotHasKey('fix_new_tab', $ext);
    }
}
