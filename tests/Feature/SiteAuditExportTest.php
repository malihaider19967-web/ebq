<?php

namespace Tests\Feature;

use App\Models\CrawlFinding;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsitePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteAuditExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_returns_a_branded_pdf_with_open_findings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'example.com']);
        $page = WebsitePage::create([
            'crawl_site_id' => $website->crawl_site_id, 'url' => 'https://example.com/broken',
            'url_hash' => WebsitePage::hashUrl('https://example.com/broken'), 'http_status' => 404,
            'is_indexable' => false, 'last_crawled_at' => now(),
        ]);

        CrawlFinding::create([
            'crawl_site_id' => $website->crawl_site_id, 'page_id' => $page->id,
            'category' => CrawlFinding::CATEGORY_BROKEN_LINK, 'type' => 'broken_page', 'severity' => 'high', 'impact' => 0,
            'affected_url' => $page->url, 'affected_url_hash' => $page->url_hash,
            'detail' => [], 'status' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_website_id' => $website->id])
            ->get(route('site-audit.download'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_download_redirects_away_for_a_user_with_no_websites_at_all(): void
    {
        // The `feature:link_structure` middleware self-heals a forged/stale
        // current_website_id to the user's own first accessible site before
        // the controller ever runs — so a user with zero accessible websites
        // is the only reachable "no access" case here; it never reaches our
        // own abort_unless(403) (kept as defense-in-depth, not as a normally
        // reachable path).
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $website = Website::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)
            ->withSession(['current_website_id' => $website->id])
            ->get(route('site-audit.download'))
            ->assertRedirect(route('onboarding'));
    }
}
