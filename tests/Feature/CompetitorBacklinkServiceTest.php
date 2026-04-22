<?php

namespace Tests\Feature;

use App\Jobs\FetchCompetitorBacklinks;
use App\Models\CompetitorBacklink;
use App\Services\CompetitorBacklinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompetitorBacklinkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.dataforseo.login' => 'test-login',
            'services.dataforseo.password' => 'test-password',
            'services.dataforseo.base_url' => 'https://api.dataforseo.com',
            'services.competitor_backlinks.fresh_days' => 30,
            'services.competitor_backlinks.limit_per_competitor' => 50,
        ]);
    }

    public function test_extract_domain_normalizes_urls(): void
    {
        $this->assertSame('example.com', CompetitorBacklink::extractDomain('https://www.example.com/path?q=1'));
        $this->assertSame('example.com', CompetitorBacklink::extractDomain('http://example.com'));
        $this->assertSame('sub.example.com', CompetitorBacklink::extractDomain('https://sub.example.com/page'));
        $this->assertSame('example.com', CompetitorBacklink::extractDomain('example.com'));
        $this->assertSame('', CompetitorBacklink::extractDomain(''));
    }

    public function test_is_fresh_returns_false_when_no_data(): void
    {
        $this->assertFalse(app(CompetitorBacklinkService::class)->isFresh('example.com'));
    }

    public function test_is_fresh_returns_true_when_row_not_expired(): void
    {
        CompetitorBacklink::create([
            'competitor_domain' => 'example.com',
            'referring_page_url' => 'https://other.com/a',
            'referring_page_hash' => CompetitorBacklink::hashUrl('https://other.com/a'),
            'fetched_at' => Carbon::now()->subDays(5),
            'expires_at' => Carbon::now()->addDays(25),
        ]);

        $this->assertTrue(app(CompetitorBacklinkService::class)->isFresh('example.com'));
    }

    public function test_is_fresh_returns_false_when_row_expired(): void
    {
        CompetitorBacklink::create([
            'competitor_domain' => 'example.com',
            'referring_page_url' => 'https://other.com/a',
            'referring_page_hash' => CompetitorBacklink::hashUrl('https://other.com/a'),
            'fetched_at' => Carbon::now()->subDays(40),
            'expires_at' => Carbon::now()->subDays(10),
        ]);

        $this->assertFalse(app(CompetitorBacklinkService::class)->isFresh('example.com'));
    }

    public function test_queue_refresh_skips_fresh_domains_and_dispatches_stale_ones(): void
    {
        Queue::fake();

        CompetitorBacklink::create([
            'competitor_domain' => 'cached.com',
            'referring_page_url' => 'https://x.com/a',
            'referring_page_hash' => CompetitorBacklink::hashUrl('https://x.com/a'),
            'fetched_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(25),
        ]);

        app(CompetitorBacklinkService::class)->queueRefresh([
            'https://cached.com/page',
            'https://uncached.com/other',
        ]);

        Queue::assertPushed(FetchCompetitorBacklinks::class, function ($job) {
            return in_array('uncached.com', $job->domains, true)
                && ! in_array('cached.com', $job->domains, true);
        });
    }

    public function test_refresh_upserts_dataforseo_response(): void
    {
        Http::fake([
            '*dataforseo.com*' => Http::response([
                'tasks' => [[
                    'status_code' => 20000,
                    'result' => [[
                        'items' => [
                            ['url_from' => 'https://news.com/article', 'domain_from' => 'news.com', 'anchor' => 'read more', 'domain_from_rank' => 72, 'dofollow' => true, 'first_seen' => '2025-06-01'],
                            ['url_from' => 'https://blog.io/post', 'domain_from' => 'blog.io', 'anchor' => 'see this', 'domain_from_rank' => 54, 'dofollow' => false, 'first_seen' => '2025-07-15'],
                        ],
                    ]],
                ]],
            ], 200),
        ]);

        $written = app(CompetitorBacklinkService::class)->refresh('example.com');

        $this->assertSame(2, $written);
        $rows = CompetitorBacklink::query()->where('competitor_domain', 'example.com')->get();
        $this->assertCount(2, $rows);

        $top = $rows->firstWhere('domain_authority', 72);
        $this->assertNotNull($top);
        $this->assertSame('news.com', $top->referring_domain);
        $this->assertSame('read more', $top->anchor_text);
        $this->assertSame('dofollow', $top->backlink_type);
        $this->assertTrue($top->fetched_at->isToday());
    }

    public function test_refresh_prunes_rows_absent_from_latest_fetch(): void
    {
        // Stale row that won't appear in the new response.
        CompetitorBacklink::create([
            'competitor_domain' => 'example.com',
            'referring_page_url' => 'https://old.com/gone',
            'referring_page_hash' => CompetitorBacklink::hashUrl('https://old.com/gone'),
            'fetched_at' => Carbon::now()->subDays(40),
            'expires_at' => Carbon::now()->subDays(10),
        ]);

        Http::fake([
            '*dataforseo.com*' => Http::response([
                'tasks' => [[
                    'status_code' => 20000,
                    'result' => [[
                        'items' => [
                            ['url_from' => 'https://news.com/article', 'domain_from' => 'news.com', 'anchor' => 'x', 'domain_from_rank' => 60, 'dofollow' => true],
                        ],
                    ]],
                ]],
            ], 200),
        ]);

        app(CompetitorBacklinkService::class)->refresh('example.com');

        $domains = CompetitorBacklink::query()->where('competitor_domain', 'example.com')->pluck('referring_domain')->all();
        $this->assertSame(['news.com'], $domains);
    }

    public function test_refresh_respects_limit_config(): void
    {
        config(['services.competitor_backlinks.limit_per_competitor' => 3]);

        $items = [];
        for ($i = 0; $i < 10; $i++) {
            $items[] = ['url_from' => "https://s{$i}.com/p", 'domain_from' => "s{$i}.com", 'anchor' => "a{$i}", 'domain_from_rank' => 50 - $i, 'dofollow' => true];
        }

        Http::fake([
            '*dataforseo.com*' => Http::response([
                'tasks' => [['status_code' => 20000, 'result' => [['items' => $items]]]],
            ], 200),
        ]);

        $written = app(CompetitorBacklinkService::class)->refresh('example.com');

        $this->assertSame(3, $written);
        $this->assertSame(3, CompetitorBacklink::query()->where('competitor_domain', 'example.com')->count());
    }

    public function test_refresh_is_a_noop_when_credentials_missing(): void
    {
        config(['services.dataforseo.login' => '']);
        Http::fake();

        $written = app(CompetitorBacklinkService::class)->refresh('example.com');

        $this->assertSame(0, $written);
        Http::assertNothingSent();
    }
}
