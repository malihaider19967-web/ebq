<?php

namespace App\Services\Crawler;

use App\Models\CrawlFinding;
use App\Models\CrawlRun;
use App\Models\CrawlSite;
use App\Models\SearchConsoleData;
use App\Models\WebsitePage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Produces the unified SEO issue catalog (crawl_findings) from the persisted
 * crawl inventory + link graph. Idempotent: findings are upserted by
 * (website_id, type, affected_url_hash); the caller resolves stale ones.
 */
class SiteIssueDetector
{
    /** Referrer samples attached to a broken target so the user can find the link. */
    private const REFERRER_SAMPLE_CAP = 5;

    /** Hard bound on referrer-edge rows scanned (pathological inbound fan-in). */
    private const REFERRER_SCAN_CAP = 50000;

    /** @var array<int,array<string,mixed>> rows pending upsert, keyed by type|hash */
    private array $pending = [];

    /** @var array<string,int> url_hash => 28d GSC clicks */
    private array $clicks = [];

    /**
     * Provenance for every 4xx/5xx target: how many internal pages link to it and
     * a sample of those referrers (url + anchor). Lets broken_page/broken_internal
     * findings answer "where is this linked from?" instead of just "it's 404".
     *
     * @var array<string,array{count:int,samples:array<int,array{url:string,anchor:?string}>}>
     */
    private array $referrers = [];

    public function __construct(private readonly LinkChecker $linkChecker) {}

    public function detect(CrawlSite $website, CrawlRun $run): int
    {
        $this->pending = [];
        $this->loadClicks($website);
        $this->loadBrokenReferrers($website);

        $deepThreshold = (int) config('crawler.deep_page_threshold', 3);
        $importantClicks = (int) config('crawler.important_clicks', 1);
        $homepageHash = WebsitePage::hashUrl($website->homepageUrl());

        // Map of page_id => [http_status, url, clicks] for broken-internal lookups.
        $statusByHash = [];

        WebsitePage::where('crawl_site_id', $website->id)
            ->whereNull('removed_at')
            ->chunkById(500, function ($pages) use ($website, $run, $deepThreshold, $importantClicks, $homepageHash, &$statusByHash): void {
                foreach ($pages as $page) {
                    $statusByHash[$page->url_hash] = ['status' => $page->http_status, 'url' => $page->url, 'id' => $page->id];
                    // Pages not yet crawled (stubs) carry no signal.
                    if ($page->last_crawled_at === null) {
                        continue;
                    }
                    $this->detectForPage($website, $run, $page, $deepThreshold, $importantClicks, $homepageHash);
                }
            });

        $this->detectBrokenInternalLinks($website, $run, $statusByHash);
        $this->detectBrokenExternalLinks($website, $run);

        return $this->flush($website->id, $run->id);
    }

    private function detectForPage(CrawlSite $website, CrawlRun $run, WebsitePage $page, int $deepThreshold, int $importantClicks, string $homepageHash): void
    {
        $clicks = $this->clicks[$page->url_hash] ?? 0;
        $status = (int) $page->http_status;
        $signals = $page->seo_signals ?? [];
        $sev = fn (string $withClicks, string $without) => $clicks >= max(1, $importantClicks) ? $withClicks : $without;

        // Broken page (URL known to GSC/sitemap/internal links returns 4xx/5xx).
        // Carry discovery provenance so the fix surface can say where it came from
        // (which internal pages link to it, the sitemap, or Search Console history)
        // instead of leaving the user with a bare 404 and nowhere to look.
        if ($status >= 400) {
            $ref = $this->referrers[$page->url_hash] ?? null;
            $detail = ['http_status' => $status, 'inbound_internal' => $ref['count'] ?? 0];
            if ($page->source_sitemap) {
                $detail['source_sitemap'] = true;
            }
            if ($page->source_gsc) {
                $detail['source_gsc'] = true;
            }
            if ($page->discovered_at) {
                $detail['discovered_at'] = $page->discovered_at->toIso8601String();
            }
            if (($ref['samples'] ?? []) !== []) {
                $detail['referrers'] = $ref['samples'];
            }
            $this->add($page, CrawlFinding::CATEGORY_BROKEN_LINK, 'broken_page', $sev('critical', 'high'), $clicks, $detail);

            return; // no point checking on-page signals for a dead page
        }

        // Redirecting URL in the frontier.
        if ($page->redirect_target) {
            $this->add($page, CrawlFinding::CATEGORY_REDIRECT, 'redirecting_url', $sev('medium', 'low'), $clicks, ['redirect_target' => $page->redirect_target]);
        }

        $indexable = (bool) $page->is_indexable && $status === 200 && ! $page->redirect_target;

        // Indexability.
        if ($status === 200 && ! $page->is_indexable && ! $page->redirect_target && str_contains((string) $page->robots_directives, 'noindex')) {
            if ($clicks >= max(1, $importantClicks)) {
                $this->add($page, CrawlFinding::CATEGORY_INDEXABILITY, 'noindex_important', 'high', $clicks, ['robots' => $page->robots_directives]);
            }
        }
        // A page canonicalizing to a different URL is usually intentional dedup
        // (e.g. ?name=… variants → the clean page) and not an issue. Only flag it
        // when the canonicalized URL still earns search traffic — i.e. Google
        // ranks a URL you're telling it to drop.
        if (($signals['canonical_points_away'] ?? false) === true && $clicks >= max(1, $importantClicks)) {
            $this->add($page, CrawlFinding::CATEGORY_INDEXABILITY, 'canonical_mismatch', 'high', $clicks, ['canonical' => $page->canonical_url]);
        }

        if (! $indexable) {
            return; // remaining checks only meaningful for live, indexable pages
        }

        // On-page SEO.
        $title = (string) $page->title;
        $titleLen = mb_strlen($title);
        if ($title === '') {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'missing_title', $sev('high', 'medium'), $clicks);
        } elseif ($titleLen > 60) {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'title_too_long', 'low', $clicks, ['length' => $titleLen]);
        } elseif ($titleLen < 15) {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'title_too_short', 'low', $clicks, ['length' => $titleLen]);
        }

        $metaLen = mb_strlen((string) $page->meta_description);
        if ($metaLen === 0) {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'missing_meta_description', $sev('medium', 'low'), $clicks);
        } elseif ($metaLen > 160) {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'meta_description_too_long', 'low', $clicks, ['length' => $metaLen]);
        }

        $h1 = (int) ($signals['h1_count'] ?? 0);
        if ($h1 === 0) {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'missing_h1', $sev('medium', 'low'), $clicks);
        } elseif ($h1 > 1) {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'multiple_h1', 'low', $clicks, ['count' => $h1]);
        }
        if (($signals['heading_order_ok'] ?? true) === false) {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'broken_heading_order', 'low', $clicks);
        }

        if ((int) $page->word_count > 0 && (int) $page->word_count < 200) {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'thin_content', $sev('medium', 'low'), $clicks, ['word_count' => (int) $page->word_count]);
        }

        if ((int) ($signals['missing_alt_count'] ?? 0) > 0) {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'missing_image_alt', 'low', $clicks, ['count' => (int) $signals['missing_alt_count']]);
        }
        if ((int) ($signals['og_tag_count'] ?? 0) === 0) {
            $this->add($page, CrawlFinding::CATEGORY_ONPAGE, 'missing_open_graph', 'low', $clicks);
        }

        // Schema.
        if (($signals['schema_types'] ?? []) === []) {
            $this->add($page, CrawlFinding::CATEGORY_SCHEMA, 'missing_structured_data', 'low', $clicks);
        }

        // Internal-link structure. Skip the homepage and skip query-string URLs
        // (e.g. ?name=…/?nick=… parameter variants): a specific parameter combo
        // having no inbound internal links is expected, not an actionable orphan,
        // and would otherwise flood the report with noise.
        $hasQuery = parse_url($page->url, PHP_URL_QUERY) !== null;
        if ($page->url_hash !== $homepageHash && ! $hasQuery) {
            // Orphan = no inbound internal links AND not listed in the sitemap.
            // Sitemap-listed pages are discoverable by search engines regardless of
            // internal links, so flagging them floods the report (tens of thousands
            // on large programmatic sites) for little actionable value.
            if ((int) $page->inbound_link_count === 0 && ! $page->source_sitemap) {
                $this->add($page, CrawlFinding::CATEGORY_INTERNAL_LINKS, 'orphan_page', $sev('high', 'medium'), $clicks);
            }
            if ($page->click_depth !== null && (int) $page->click_depth >= $deepThreshold) {
                $this->add($page, CrawlFinding::CATEGORY_INTERNAL_LINKS, 'deep_page', $sev('medium', 'low'), $clicks, ['click_depth' => (int) $page->click_depth]);
            }
        }

        // Sitemap hygiene: GSC-trafficked + indexable but absent from sitemap.
        if ($page->source_gsc && ! $page->source_sitemap) {
            $this->add($page, CrawlFinding::CATEGORY_SITEMAP, 'indexed_not_in_sitemap', 'low', $clicks);
        }
    }

    /**
     * Internal links pointing at pages that return 4xx/5xx — the on-site broken
     * links that hurt crawl + UX. Derived from the inventory (every internal
     * target is itself crawled), so no extra network calls.
     *
     * @param  array<string,array{status:?int,url:string,id:int}>  $statusByHash
     */
    private function detectBrokenInternalLinks(CrawlSite $website, CrawlRun $run, array $statusByHash): void
    {
        DB::table('website_internal_links as l')
            ->join('website_pages as t', 't.id', '=', 'l.to_page_id')
            ->where('l.crawl_site_id', $website->id)
            ->where('l.status', 'discovered')
            ->where('t.http_status', '>=', 400)
            ->select('t.id', 't.url', 't.url_hash', 't.http_status', DB::raw('COUNT(*) as inbound'))
            ->groupBy('t.id', 't.url', 't.url_hash', 't.http_status')
            ->orderByDesc('inbound')
            ->chunk(500, function ($rows) use ($website, $run): void {
                foreach ($rows as $r) {
                    $key = 'broken_internal|'.$r->url_hash;
                    $detail = ['http_status' => (int) $r->http_status, 'inbound_links' => (int) $r->inbound];
                    $samples = $this->referrers[$r->url_hash]['samples'] ?? [];
                    if ($samples !== []) {
                        $detail['referrers'] = $samples;
                    }
                    $this->pending[$key] = $this->row(
                        $website->id, $run->id, (int) $r->id,
                        CrawlFinding::CATEGORY_BROKEN_LINK, 'broken_internal',
                        ($this->clicks[$r->url_hash] ?? 0) > 0 ? 'critical' : 'high',
                        0.0, // per-user impact computed read-time
                        $r->url,
                        $detail,
                    );
                }
            });
    }

    /**
     * Bounded broken-external-link pass: check the sampled external links stored
     * on each page (capped per site) and flag 4xx/5xx + redirects.
     */
    private function detectBrokenExternalLinks(CrawlSite $website, CrawlRun $run): void
    {
        $cap = (int) config('crawler.max_external_checks', 500);
        if ($cap <= 0) {
            return;
        }

        $links = [];
        WebsitePage::where('crawl_site_id', $website->id)
            ->whereNull('removed_at')
            ->whereNotNull('seo_signals')
            ->select('id', 'seo_signals')
            ->chunkById(500, function ($pages) use (&$links, $cap): void {
                foreach ($pages as $page) {
                    foreach (($page->seo_signals['external_links'] ?? []) as $l) {
                        $href = (string) ($l['href'] ?? '');
                        if ($href === '' || isset($links[$href])) {
                            continue;
                        }
                        $links[$href] = ['href' => $href, 'anchor' => (string) ($l['anchor'] ?? ''), 'from' => (int) $page->id];
                    }
                }
            });

        if ($links === []) {
            return;
        }

        $sample = array_slice(array_values($links), 0, $cap);
        $problems = $this->linkChecker->check($sample, $cap);

        foreach ($problems as $p) {
            $href = (string) $p['href'];
            $fromId = $links[$href]['from'] ?? null;
            if (! empty($p['redirected'])) {
                $this->pending['ext_redirect|'.CrawlFinding::hashUrl($href)] = $this->row(
                    $website->id, $run->id, $fromId,
                    CrawlFinding::CATEGORY_REDIRECT, 'external_redirect', 'low', 0.0, $href,
                    ['final_url' => $p['final_url'], 'chain' => $p['chain'], 'anchor' => $p['anchor']],
                );
            }
            $status = $p['status'];
            if ($status === null || $status >= 400) {
                $this->pending['broken_external|'.CrawlFinding::hashUrl($href)] = $this->row(
                    $website->id, $run->id, $fromId,
                    CrawlFinding::CATEGORY_BROKEN_LINK, 'broken_external', 'medium', 0.0, $href,
                    ['http_status' => $status, 'error' => $p['error'], 'anchor' => $p['anchor']],
                );
            }
        }
    }

    private function add(WebsitePage $page, string $category, string $type, string $severity, int $clicks, array $detail = []): void
    {
        // Stored impact is 0: the shared finding must not carry any user's (or the
        // aggregate's) click count. Per-user impact is computed read-time in
        // CrawlReportService from each website's own SearchConsoleData.
        $this->pending[$type.'|'.$page->url_hash] = $this->row(
            $page->crawl_site_id, 0, $page->id, $category, $type, $severity, 0.0, $page->url, $detail,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function row(string $crawlSiteId, string $runId, ?string $pageId, string $category, string $type, string $severity, float $impact, string $url, array $detail): array
    {
        $now = now();

        return [
            'crawl_site_id' => $crawlSiteId,
            'page_id' => $pageId ?: null,
            'crawl_run_id' => $runId ?: null,
            'category' => $category,
            'type' => $type,
            'severity' => $severity,
            'impact' => $impact,
            'affected_url' => mb_substr($url, 0, 2048),
            'affected_url_hash' => CrawlFinding::hashUrl($url),
            'detail' => $detail === [] ? null : json_encode($detail),
            'status' => CrawlFinding::STATUS_OPEN,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'resolved_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function flush(string $crawlSiteId, string $runId): int
    {
        if ($this->pending === []) {
            return 0;
        }

        $rows = array_map(function (array $r) use ($runId) {
            $r['crawl_run_id'] = $r['crawl_run_id'] ?: $runId;

            return $r;
        }, array_values($this->pending));

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('crawl_findings')->upsert(ulid_rows($chunk),
                ['crawl_site_id', 'type', 'affected_url_hash'],
                ['page_id', 'crawl_run_id', 'category', 'severity', 'impact', 'affected_url', 'detail', 'status', 'last_seen_at', 'resolved_at', 'updated_at'],
            );
        }

        return count($rows);
    }

    private function loadClicks(CrawlSite $website): void
    {
        $this->clicks = [];
        $since = Carbon::now()->subDays(28)->toDateString();

        // Aggregate clicks across ALL subscribers so a click-conditional finding
        // (e.g. noindex_important) exists in the shared catalog if ANY subscriber
        // has traffic. Per-user impact + severity are recomputed read-time.
        $subscriberIds = $website->websites()->pluck('id')->all();
        if ($subscriberIds === []) {
            return;
        }

        SearchConsoleData::query()
            ->whereIn('website_id', $subscriberIds)
            ->whereDate('date', '>=', $since)
            ->where('page', '!=', '')
            ->select('page', DB::raw('SUM(clicks) as c'))
            ->groupBy('page')
            ->chunk(2000, function ($rows): void {
                foreach ($rows as $r) {
                    $this->clicks[WebsitePage::hashUrl((string) $r->page)] = (int) $r->c;
                }
            });
    }

    /**
     * Build $this->referrers: for every internal-link edge whose target returns
     * 4xx/5xx, accumulate an inbound count + a capped sample of the source pages
     * (url + anchor) keyed by the target's url_hash. Streamed via cursor() and
     * globally bounded so a single 404 with huge fan-in can't blow up memory.
     */
    private function loadBrokenReferrers(CrawlSite $website): void
    {
        $this->referrers = [];
        $scanned = 0;

        foreach (
            DB::table('website_internal_links as l')
                ->join('website_pages as t', 't.id', '=', 'l.to_page_id')
                ->join('website_pages as f', 'f.id', '=', 'l.from_page_id')
                ->where('l.crawl_site_id', $website->id)
                ->where('l.status', 'discovered')
                ->where('t.http_status', '>=', 400)
                ->whereNull('f.removed_at')
                ->orderBy('l.id')
                ->select('t.url_hash as target_hash', 'f.url as from_url', 'l.anchor_text as anchor')
                ->cursor() as $r
        ) {
            $hash = (string) $r->target_hash;
            if (! isset($this->referrers[$hash])) {
                $this->referrers[$hash] = ['count' => 0, 'samples' => []];
            }
            $this->referrers[$hash]['count']++;
            if (count($this->referrers[$hash]['samples']) < self::REFERRER_SAMPLE_CAP) {
                $this->referrers[$hash]['samples'][] = [
                    'url' => (string) $r->from_url,
                    'anchor' => $r->anchor !== null ? (string) $r->anchor : null,
                ];
            }
            if (++$scanned >= self::REFERRER_SCAN_CAP) {
                break;
            }
        }
    }
}
