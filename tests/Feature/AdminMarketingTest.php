<?php

namespace Tests\Feature;

use App\Mail\CrawlReportMail;
use App\Models\CrawlFinding;
use App\Models\CrawlRun;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminMarketingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function completedCrawl(Website $website): void
    {
        CrawlRun::create([
            'crawl_site_id' => $website->crawl_site_id, 'trigger' => 'manual',
            'status' => CrawlRun::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(5), 'finished_at' => now(),
            'pages_fetched' => 10, 'health_score' => 55,
        ]);
    }

    private function openFinding(Website $website, string $url, string $severity = 'critical'): void
    {
        $this->finding($website, 'broken_link', 'broken_page', $severity, $url);
    }

    private function finding(Website $website, string $category, string $type, string $severity, string $url): void
    {
        CrawlFinding::create([
            'crawl_site_id' => $website->crawl_site_id, 'category' => $category, 'type' => $type,
            'severity' => $severity, 'impact' => $severity === 'critical' ? 12 : 1, 'affected_url' => $url,
            'affected_url_hash' => CrawlFinding::hashUrl($url), 'status' => 'open',
            'detail' => ['http_status' => 404], 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
    }

    public function test_index_lists_only_finished_crawls_with_open_issues(): void
    {
        $owner = User::factory()->create();

        // A: finished crawl + open finding -> shown.
        $a = Website::factory()->create(['user_id' => $owner->id, 'domain' => 'shown.com']);
        $this->completedCrawl($a);
        $this->openFinding($a, 'https://shown.com/dead');

        // B: finished crawl but NO open findings -> hidden.
        $b = Website::factory()->create(['user_id' => $owner->id, 'domain' => 'clean.com']);
        $this->completedCrawl($b);

        // C: open finding but crawl still running -> hidden.
        $c = Website::factory()->create(['user_id' => $owner->id, 'domain' => 'running.com']);
        CrawlRun::create(['crawl_site_id' => $c->crawl_site_id, 'trigger' => 'manual', 'status' => CrawlRun::STATUS_RUNNING, 'started_at' => now()]);
        $this->openFinding($c, 'https://running.com/dead');

        $this->actingAs($this->admin())
            ->get(route('admin.marketing.index'))
            ->assertOk()
            ->assertSee('shown.com')
            ->assertDontSee('clean.com')
            ->assertDontSee('running.com');
    }

    public function test_admin_send_queues_mail_and_records_the_send(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['email' => 'owner@client.com', 'name' => 'Client']);
        $website = Website::factory()->create(['user_id' => $owner->id, 'domain' => 'site.com']);
        $this->completedCrawl($website);
        $this->openFinding($website, 'https://site.com/dead');

        $this->actingAs($this->admin())
            ->post(route('admin.marketing.send', $website))
            ->assertRedirect();

        Mail::assertQueued(CrawlReportMail::class, fn ($m) => $m->hasTo('owner@client.com') && $m->website->id === $website->id);

        $this->assertDatabaseHas('crawl_report_sends', [
            'website_id' => $website->id,
            'recipient_user_id' => $owner->id,
            'to_email' => 'owner@client.com',
            'status' => 'sent',
        ]);
        // The numbers + per-category breakdown snapshot is recorded.
        $send = \App\Models\CrawlReportSend::first();
        $this->assertSame(1, (int) ($send->summary['counts']['total'] ?? 0));
        $bl = collect($send->summary['breakdown'])->firstWhere('key', 'broken_link');
        $this->assertNotNull($bl);
        $this->assertSame(1, (int) $bl['count']);
        $this->assertCount(1, $bl['examples']);
    }

    public function test_breakdown_dedupes_urls_and_includes_growth_and_low_issues(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $owner->id, 'domain' => 'site.com']);
        $this->completedCrawl($website);

        // Same URL flagged as BOTH broken_page and broken_internal — the basepaws
        // duplicate. It must appear once in the broken-link examples.
        $dup = 'https://site.com/dead';
        $this->finding($website, 'broken_link', 'broken_page', 'critical', $dup);
        $this->finding($website, 'broken_link', 'broken_internal', 'high', $dup);

        // A growth-tier, low-severity on-page issue that must still be included.
        $this->finding($website, 'onpage', 'missing_meta_description', 'low', 'https://site.com/about');

        $this->actingAs($this->admin())->post(route('admin.marketing.send', $website))->assertRedirect();

        $breakdown = collect(\App\Models\CrawlReportSend::first()->summary['breakdown']);

        $bl = $breakdown->firstWhere('key', 'broken_link');
        $this->assertSame(2, (int) $bl['count']);   // two finding rows...
        $this->assertCount(1, $bl['examples']);       // ...deduped to one URL

        $onpage = $breakdown->firstWhere('key', 'onpage');
        $this->assertNotNull($onpage, 'growth-tier / low-severity on-page issues must be included');
        $this->assertCount(1, $onpage['examples']);
    }

    public function test_report_snapshot_includes_gsc_traffic_when_connected(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['email' => 'owner@client.com']);
        $website = Website::factory()->create(['user_id' => $owner->id, 'domain' => 'site.com']);
        $this->completedCrawl($website);
        $this->openFinding($website, 'https://site.com/dead');

        // Search Console rows inside the lag-safe window (default 3-day GSC lag).
        foreach ([5, 6, 7] as $daysAgo) {
            \App\Models\SearchConsoleData::create([
                'website_id' => $website->id,
                'date' => now()->subDays($daysAgo)->toDateString(),
                'query' => 'cats', 'page' => 'https://site.com/',
                'clicks' => 10, 'impressions' => 100, 'position' => 4.0, 'country' => 'usa',
            ]);
        }

        $this->actingAs($this->admin())
            ->post(route('admin.marketing.send', $website))
            ->assertRedirect();

        $send = \App\Models\CrawlReportSend::first();
        $this->assertTrue($send->summary['traffic']['has_gsc'] ?? false);
        // Seeded GSC clicks flow into the snapshot (exact total depends on the
        // lag-aware window boundaries; we only assert the numbers came through).
        $this->assertGreaterThan(0, (int) ($send->summary['traffic']['gsc']['clicks'] ?? 0));
        $this->assertFalse($send->summary['traffic']['has_ga']); // no GA seeded
    }

    public function test_report_has_no_traffic_block_without_gsc_or_ga(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $owner->id, 'domain' => 'site.com']);
        $this->completedCrawl($website);
        $this->openFinding($website, 'https://site.com/dead');

        $this->actingAs($this->admin())->post(route('admin.marketing.send', $website))->assertRedirect();

        $send = \App\Models\CrawlReportSend::first();
        $this->assertNull($send->summary['traffic']);
    }

    public function test_admin_can_override_the_recipient_address(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['email' => 'owner@client.com']);
        $website = Website::factory()->create(['user_id' => $owner->id, 'domain' => 'site.com']);
        $this->completedCrawl($website);
        $this->openFinding($website, 'https://site.com/dead');

        $this->actingAs($this->admin())
            ->post(route('admin.marketing.send', $website), ['to_email' => 'someone@else.com'])
            ->assertRedirect();

        Mail::assertQueued(CrawlReportMail::class, fn ($m) => $m->hasTo('someone@else.com'));
        $this->assertDatabaseHas('crawl_report_sends', ['website_id' => $website->id, 'to_email' => 'someone@else.com']);
    }

    public function test_report_email_renders_with_traffic_and_examples(): void
    {
        // Renders the actual Blade template end-to-end — guards against directive
        // bugs (a glued @if once left a dangling endif that broke the whole email).
        $website = Website::factory()->make(['domain' => 'site.com']); // render only — no DB needed
        $report = [
            'counts' => ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4, 'total' => 10],
            'health_score' => 55,
            'traffic' => [
                'has_gsc' => true, 'has_ga' => false, 'period_label' => '28 days',
                'gsc' => ['clicks' => 120, 'clicks_change_percent' => 12.5, 'clicks_direction' => 'up', 'impressions' => 3400, 'position' => 8.2],
            ],
            'breakdown' => [[
                'key' => 'broken_link', 'title' => 'Broken links', 'severity' => 'critical', 'count' => 3, 'impact' => 5,
                'examples' => [['url' => 'https://site.com/dead', 'label' => '/dead', 'description' => 'Page returns 404', 'severity' => 'critical', 'impact' => 5]],
            ]],
            'dashboard_url' => 'https://app.test/dashboard',
        ];

        $html = (new CrawlReportMail($website, $report, 'Client'))->render();

        $this->assertStringContainsString('All issues found', $html);
        $this->assertStringContainsString('https://site.com/dead', $html); // the example URL is shown
        $this->assertStringContainsString('Page returns 404', $html);
        $this->assertStringContainsString('Clicks', $html);                // the once-broken traffic line
        $this->assertStringContainsString('120', $html);
    }

    public function test_non_admin_cannot_access_marketing_or_send(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $owner->id]);

        $user = User::factory()->create(); // not an admin
        $this->actingAs($user)->get(route('admin.marketing.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.marketing.send', $website))->assertForbidden();

        Mail::assertNothingQueued();
    }
}
