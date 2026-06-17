<?php

namespace Tests\Feature;

use App\Models\PageAuditReport;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Services\AiContentBriefService;
use App\Services\AiSnippetRewriterService;
use App\Services\StrikingDistanceFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class StrikingDistanceFixServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(?AiSnippetRewriterService $rewriter = null, ?AiContentBriefService $briefs = null): StrikingDistanceFixService
    {
        return new StrikingDistanceFixService(
            $rewriter ?? Mockery::mock(AiSnippetRewriterService::class),
            $briefs ?? Mockery::mock(AiContentBriefService::class),
        );
    }

    public function test_synthetic_post_id_is_deterministic_and_positive_31_bit(): void
    {
        $svc = $this->service();

        $a = $svc->syntheticPostId(7, 'https://example.test/page');
        $b = $svc->syntheticPostId(7, 'https://example.test/page');
        $c = $svc->syntheticPostId(8, 'https://example.test/page');

        $this->assertSame($a, $b, 'same inputs must yield the same id');
        $this->assertNotSame($a, $c, 'different website ids should differ');
        $this->assertGreaterThan(0, $a);
        $this->assertLessThanOrEqual(0x7FFFFFFF, $a);
    }

    public function test_snippet_rewrites_feeds_body_excerpt_and_competitor_titles_from_report(): void
    {
        $captured = null;
        $rewriter = Mockery::mock(AiSnippetRewriterService::class);
        $rewriter->shouldReceive('rewrite')->once()
            ->with(Mockery::type('int'), Mockery::on(function ($input) use (&$captured) {
                $captured = $input;

                return true;
            }))
            ->andReturn(['ok' => true, 'rewrites' => []]);

        $report = new PageAuditReport([
            'result' => [
                'metadata' => ['title' => 'Old title', 'meta_description' => 'Old meta'],
                'content' => ['body_excerpt' => 'The page copy about blue widgets.', 'word_count' => 800],
                'benchmark' => ['competitors' => [
                    ['title' => 'Comp A', 'word_count' => 1200],
                    ['title' => 'Comp B', 'word_count' => 1500],
                ]],
            ],
        ]);

        $out = $this->service($rewriter)->snippetRewrites(1, 'https://example.test/p', 'blue widgets', $report, 'auto');

        $this->assertTrue($out['ok']);
        $this->assertSame('blue widgets', $captured['focus_keyword']);
        $this->assertSame('Old title', $captured['current_title']);
        $this->assertNotSame('', $captured['content_excerpt'], 'body excerpt must reach the rewriter (regression guard for PageAuditService change)');
        $this->assertSame(['Comp A', 'Comp B'], $captured['competitor_titles']);
    }

    public function test_on_page_metrics_flags_keyword_presence_and_word_count_gap(): void
    {
        $report = new PageAuditReport([
            'result' => [
                'metadata' => ['title' => 'Best blue widgets guide', 'meta_description' => 'no kw here'],
                'content' => [
                    'word_count' => 600,
                    'headings' => [
                        ['level' => 1, 'text' => 'Blue widgets explained'],
                        ['level' => 2, 'text' => 'Pricing'],
                    ],
                ],
                'benchmark' => ['competitors' => [
                    ['word_count' => 1000],
                    ['word_count' => 1400],
                ]],
            ],
        ]);

        $m = $this->service()->onPageMetrics($report, 'blue widgets');

        $this->assertTrue($m['in_title']);
        $this->assertTrue($m['in_h1']);
        $this->assertFalse($m['in_meta']);
        $this->assertSame(600, $m['word_count']);
        $this->assertSame(1200, $m['competitor_word_count_median']);
        $this->assertSame(600, $m['word_count_gap']);
    }

    public function test_find_fresh_report_honours_the_age_window(): void
    {
        $website = Website::factory()->create(['user_id' => User::factory()->create()->id]);

        $fresh = $this->makeReport($website->id, 'https://example.test/fresh', Carbon::now()->subHours(2));
        $stale = $this->makeReport($website->id, 'https://example.test/stale', Carbon::now()->subHours(48));

        $svc = $this->service();

        $this->assertSame($fresh->id, $svc->findFreshReport($website->id, 'https://example.test/fresh')?->id);
        $this->assertNull($svc->findFreshReport($website->id, 'https://example.test/stale'));
    }

    public function test_internal_links_forwards_excluded_page_to_brief_service(): void
    {
        $website = Website::factory()->create(['user_id' => User::factory()->create()->id]);
        $rows = [['url' => 'https://example.test/a', 'anchor_hint' => 'blue widgets', 'clicks_30d' => 42]];

        $briefs = Mockery::mock(AiContentBriefService::class);
        $briefs->shouldReceive('internalLinkTargets')->once()
            ->with(Mockery::type(Website::class), 'blue widgets', 'https://example.test/ranking')
            ->andReturn($rows);

        $this->assertSame(
            $rows,
            $this->service(null, $briefs)->internalLinks($website, 'blue widgets', 'https://example.test/ranking'),
        );
    }

    public function test_internal_link_targets_excludes_the_ranking_page(): void
    {
        $website = Website::factory()->create(['user_id' => User::factory()->create()->id]);
        $ranking = 'https://example.test/widgets';
        $other = 'https://example.test/blog/widget-guide';

        foreach ([$ranking, $other] as $page) {
            SearchConsoleData::create([
                'website_id' => $website->id,
                'date' => Carbon::yesterday()->toDateString(),
                'query' => 'blue widgets',
                'page' => $page,
                'clicks' => 25,
                'impressions' => 400,
            ]);
        }

        $briefs = app(AiContentBriefService::class);

        $withExclusion = collect($briefs->internalLinkTargets($website, 'blue widgets', $ranking))->pluck('url')->all();
        $this->assertContains($other, $withExclusion);
        $this->assertNotContains($ranking, $withExclusion, 'the ranking page must not be suggested as a self-link');

        $without = collect($briefs->internalLinkTargets($website, 'blue widgets'))->pluck('url')->all();
        $this->assertContains($ranking, $without, 'without exclusion the ranking page is still returned (brief flow unchanged)');
    }

    private function makeReport(string $websiteId, string $url, Carbon $auditedAt): PageAuditReport
    {
        return PageAuditReport::create([
            'website_id' => $websiteId,
            'page' => $url,
            'page_hash' => hash('sha256', $url),
            'status' => 'completed',
            'audited_at' => $auditedAt,
            'result' => ['recommendations' => []],
        ]);
    }
}
