<?php

namespace App\Services\Crawler;

use App\Models\CrawlFinding;
use App\Models\CrawlRun;
use App\Models\Website;
use App\Models\WebsiteInternalLink;
use App\Models\WebsitePage;
use App\Services\ReportCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregation over the crawl tables. Shared by the Site Health page,
 * the Priority Action Queue, growth reports, and the AI context builder so
 * every surface reads one consistent view of the crawl intelligence.
 */
class CrawlReportService
{
    /** Display metadata + dashboard severity per finding category. */
    private const CATEGORIES = [
        CrawlFinding::CATEGORY_CRAWLABILITY => ['title' => 'Crawler blocked', 'desc' => 'Our crawler is being blocked by the site (CAPTCHA / 403 / 429).', 'sev' => 'critical'],
        CrawlFinding::CATEGORY_BROKEN_LINK => ['title' => 'Broken links', 'desc' => 'Internal or external links returning 4xx/5xx errors.', 'sev' => 'high'],
        CrawlFinding::CATEGORY_INDEXABILITY => ['title' => 'Indexability issues', 'desc' => 'noindex on traffic pages, canonical mismatches and similar.', 'sev' => 'high'],
        CrawlFinding::CATEGORY_INTERNAL_LINKS => ['title' => 'Internal-link issues', 'desc' => 'Orphan pages and pages buried too deep in the site.', 'sev' => 'high'],
        CrawlFinding::CATEGORY_REDIRECT => ['title' => 'Redirects', 'desc' => 'Redirecting URLs and redirect chains.', 'sev' => 'growth'],
        CrawlFinding::CATEGORY_ONPAGE => ['title' => 'On-page SEO issues', 'desc' => 'Missing/duplicate titles, meta, H1s, thin content, alt text.', 'sev' => 'growth'],
        CrawlFinding::CATEGORY_SITEMAP => ['title' => 'Sitemap issues', 'desc' => 'Sitemap coverage gaps and invalid sitemap URLs.', 'sev' => 'growth'],
        CrawlFinding::CATEGORY_SCHEMA => ['title' => 'Structured data', 'desc' => 'Pages missing schema.org structured data.', 'sev' => 'growth'],
    ];

    /**
     * Site-level health overview for a website.
     *
     * @return array<string,mixed>
     */
    public function summary(int $websiteId): array
    {
        $run = CrawlRun::where('website_id', $websiteId)->latest('started_at')->first();

        $bySeverity = CrawlFinding::where('website_id', $websiteId)->where('status', 'open')
            ->select('severity', DB::raw('COUNT(*) as c'))->groupBy('severity')->pluck('c', 'severity');

        $pagesTotal = WebsitePage::where('website_id', $websiteId)->whereNotNull('last_crawled_at')->whereNull('removed_at')->count();
        $indexable = WebsitePage::where('website_id', $websiteId)->indexable()->whereNotNull('last_crawled_at')->count();
        $orphans = WebsitePage::where('website_id', $websiteId)->orphans()->count();

        return [
            'has_crawl' => $run !== null,
            'health_score' => $run?->health_score,
            'last_crawled_at' => $run?->finished_at ?? $run?->started_at,
            'run_status' => $run?->status,
            'blocked' => $run?->isBlocked() ?? false,
            'blocked_reason' => $run?->blocked_reason,
            'pages_total' => $pagesTotal,
            'pages_indexable' => $indexable,
            'orphan_count' => $orphans,
            'findings' => [
                'critical' => (int) ($bySeverity['critical'] ?? 0),
                'high' => (int) ($bySeverity['high'] ?? 0),
                'medium' => (int) ($bySeverity['medium'] ?? 0),
                'low' => (int) ($bySeverity['low'] ?? 0),
                'total' => (int) $bySeverity->sum(),
            ],
        ];
    }

    /**
     * Open findings grouped by category, ranked. Shaped to merge straight into
     * the Priority Action Queue (key/title/description/count/severity/impact).
     *
     * @return array<int,array<string,mixed>>
     */
    public function actionGroups(int $websiteId): array
    {
        $rows = CrawlFinding::where('website_id', $websiteId)->where('status', 'open')
            ->select('category', DB::raw('COUNT(*) as c'), DB::raw('SUM(impact) as impact'),
                DB::raw("SUM(CASE WHEN severity='critical' THEN 1 ELSE 0 END) as crit"),
                DB::raw("SUM(CASE WHEN severity='high' THEN 1 ELSE 0 END) as high"))
            ->groupBy('category')->get();

        $groups = [];
        foreach ($rows as $row) {
            $meta = self::CATEGORIES[$row->category] ?? ['title' => ucfirst($row->category), 'desc' => '', 'sev' => 'growth'];
            // Escalate the group severity if it contains more severe findings.
            $sev = $meta['sev'];
            if ((int) $row->crit > 0) {
                $sev = 'critical';
            } elseif ((int) $row->high > 0 && $sev === 'growth') {
                $sev = 'high';
            }
            $groups[] = [
                'key' => 'crawl_'.$row->category,
                'title' => $meta['title'],
                'description' => $meta['desc'],
                'count' => (int) $row->c,
                'severity' => $sev,
                'impact' => (float) $row->impact,
                'impact_label' => null,
                'action_label' => 'View',
            ];
        }

        return $groups;
    }

    /**
     * Detail rows for one crawl category (Priority Action Queue slide-over).
     *
     * @return array<int,array<string,mixed>>
     */
    public function issueRows(string $category, int $websiteId): array
    {
        return $this->issuesQuery($category, $websiteId)
            ->limit(100) // slide-over shows the top 100 (header notes "showing first N")
            ->get()
            ->map(fn (CrawlFinding $f): array => $this->mapFinding($f))
            ->all();
    }

    /**
     * Base query for the open findings in one crawl category, severity/impact
     * ordered and eager-loading the source page. Optional filters:
     *   type     — restrict to one finding type (e.g. 'orphan_page')
     *   severity — restrict to one severity
     *   q        — substring match on the affected URL
     * Returns the builder so callers can ->paginate() the (potentially huge) set.
     */
    public function issuesQuery(string $category, int $websiteId, array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $q = CrawlFinding::where('website_id', $websiteId)->where('status', 'open')
            ->where('category', $category)
            ->with('page:id,url'); // the source/affected page (for external links: where the link lives)

        if (! empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }
        if (! empty($filters['severity'])) {
            $q->where('severity', $filters['severity']);
        }
        if (! empty($filters['q'])) {
            $q->where('affected_url', 'like', '%'.$filters['q'].'%');
        }

        // Order by impact (clicks-at-risk) desc — index-served via the
        // (website_id, category, status, [type,] impact) indexes, so paginating a
        // 100k-finding category streams 50 rows instead of filesorting the lot.
        // Severity is a filter on the page, so it doesn't drive the default sort.
        return $q->orderByDesc('impact')->orderByDesc('id');
    }

    /** Open-finding counts per type within a category — drives the type filter. */
    public function typeCounts(string $category, int $websiteId): array
    {
        return CrawlFinding::where('website_id', $websiteId)->where('status', 'open')
            ->where('category', $category)
            ->select('type', DB::raw('COUNT(*) as c'))
            ->groupBy('type')->orderByDesc('c')
            ->pluck('c', 'type')->all();
    }

    /** Human label for a finding type, e.g. 'missing_image_alt' → 'Missing image alt'. */
    public function typeLabel(string $type): string
    {
        return ucfirst(str_replace('_', ' ', $type));
    }

    /** Normalize one finding into the uniform detail-row shape used by the UI. */
    public function mapFinding(CrawlFinding $f): array
    {
        // External links aren't in our inventory, so Link Structure has no data for
        // them. Instead show WHICH page the link is on and let "Fix" open that
        // source page so the user can find/remove the link.
        if (in_array($f->type, ['broken_external', 'external_redirect'], true)) {
            $source = $f->page?->url;

            return [
                'title' => $f->affected_url,
                'subtitle' => ($source ? 'On '.$this->shortUrl($source).' · ' : '').$this->describe($f),
                'metric' => null,
                'fix_url' => $source, // opens the page containing the broken link
                'fix_feature' => 'link_structure',
                'fix_new_tab' => true, // it's the client's live page — open in a new tab
            ];
        }

        return [
            'title' => $this->shortUrl($f->affected_url),
            'subtitle' => $this->describe($f),
            'metric' => $f->impact > 0 ? number_format((int) $f->impact).' clicks (28d)' : null,
            'fix_url' => route('link-structure.index', ['url' => $f->affected_url]),
            'fix_feature' => 'link_structure',
        ];
    }

    /**
     * Full finding detail for one category (Site Health expandable list).
     *
     * @return array<int,array<string,mixed>>
     */
    public function categoryFindings(string $category, int $websiteId, int $limit = 200): array
    {
        return CrawlFinding::where('website_id', $websiteId)->where('status', 'open')
            ->where('category', $category)
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByDesc('impact')
            ->limit($limit)
            ->get()
            ->map(fn (CrawlFinding $f): array => [
                'url' => $f->affected_url,
                'label' => $this->shortUrl($f->affected_url),
                'description' => $this->describe($f),
                'severity' => $f->severity,
                'impact' => (int) $f->impact,
                'detail' => $f->detail ?? [],
            ])->all();
    }

    /**
     * Full per-category issue breakdown for the admin Marketing report email:
     * EVERY open category — including the growth-tier on-page / redirect /
     * sitemap / schema groups, not just critical/high — ordered by severity,
     * each with its total count and a few example URLs. URLs are de-duplicated
     * within a category so a page that trips two finding types (e.g. a 404 that
     * is both broken_page and broken_internal) is listed once, not twice.
     *
     * @return array<int,array<string,mixed>>
     */
    public function reportBreakdown(int $websiteId, int $perCategory = 5): array
    {
        $rank = ['critical' => 0, 'high' => 1, 'growth' => 2, 'medium' => 2, 'low' => 3];
        $groups = $this->actionGroups($websiteId);
        usort($groups, fn ($a, $b) => [$rank[$a['severity']] ?? 9, -$a['impact']] <=> [$rank[$b['severity']] ?? 9, -$b['impact']]);

        $out = [];
        foreach ($groups as $g) {
            $category = \Illuminate\Support\Str::after($g['key'], 'crawl_');
            $out[] = [
                'key' => $category,
                'title' => $g['title'],
                'severity' => $g['severity'],
                'count' => (int) $g['count'],
                'impact' => (int) $g['impact'],
                // Pull extra rows then de-dupe by URL so we still land $perCategory
                // distinct pages even when several findings share a URL.
                'examples' => $this->dedupeByUrl(
                    $this->categoryFindings($category, $websiteId, max($perCategory * 4, 12)),
                    $perCategory,
                ),
            ];
        }

        return $out;
    }

    /**
     * Keep the first finding per affected URL (input is already severity/impact
     * ordered), capped at $limit — so the same URL never appears twice.
     *
     * @param  array<int,array<string,mixed>>  $findings
     * @return array<int,array<string,mixed>>
     */
    private function dedupeByUrl(array $findings, int $limit): array
    {
        $seen = [];
        $out = [];
        foreach ($findings as $f) {
            $url = (string) ($f['url'] ?? $f['label'] ?? '');
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $out[] = [
                'url' => $f['url'] ?? null,
                'label' => $f['label'] ?? null,
                'description' => $f['description'] ?? '',
                'severity' => $f['severity'] ?? 'low',
                'impact' => (int) ($f['impact'] ?? 0),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** Paginated page inventory for the Site Health page. */
    public function inventory(int $websiteId, string $filter = 'all')
    {
        $q = WebsitePage::where('website_id', $websiteId)->whereNull('removed_at')->whereNotNull('last_crawled_at');

        match ($filter) {
            'orphans' => $q->orphans(),
            'broken' => $q->where('http_status', '>=', 400),
            'noindex' => $q->where('is_indexable', false),
            'deep' => $q->where('click_depth', '>=', (int) config('crawler.deep_page_threshold', 3)),
            default => null,
        };

        return $q->orderByDesc('inbound_link_count')->orderBy('url');
    }

    /**
     * Compact internal-link graph for the diagram (capped).
     *
     * @return array{nodes:array<int,array<string,mixed>>,edges:array<int,array<string,int>>}
     */
    public function linkGraph(int $websiteId, int $cap = 120): array
    {
        $pages = WebsitePage::where('website_id', $websiteId)
            ->whereNotNull('last_crawled_at')->whereNull('removed_at')
            ->orderByDesc('inbound_link_count')
            ->limit($cap)
            ->get(['id', 'url', 'inbound_link_count', 'click_depth', 'is_indexable']);

        $ids = $pages->pluck('id')->all();
        $nodes = $pages->map(fn (WebsitePage $p) => [
            'id' => $p->id,
            'label' => $this->shortUrl($p->url),
            'inbound' => (int) $p->inbound_link_count,
            'depth' => $p->click_depth,
            'indexable' => (bool) $p->is_indexable,
        ])->all();

        $edges = WebsiteInternalLink::where('website_id', $websiteId)
            ->where('status', 'discovered')
            ->whereIn('from_page_id', $ids)->whereIn('to_page_id', $ids)
            ->limit(600)
            ->get(['from_page_id', 'to_page_id'])
            ->map(fn ($e) => ['from' => (int) $e->from_page_id, 'to' => (int) $e->to_page_id])
            ->all();

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Per-page internal-link structure for the Link Structure page: the page's
     * own stats plus its inbound links (pages linking to it), outbound internal
     * links (pages it links to), and AI-suggested links — discovered + suggested.
     *
     * @return array<string,mixed>|null  null when the URL isn't in the inventory
     */
    public function pageLinkStructure(int $websiteId, string $url): ?array
    {
        $page = WebsitePage::where('website_id', $websiteId)
            ->where('url_hash', WebsitePage::hashUrl($url))->first();
        if (! $page) {
            return null;
        }

        $inbound = WebsiteInternalLink::where('to_page_id', $page->id)
            ->where('status', 'discovered')->with('fromPage:id,url,http_status,is_indexable')
            ->limit(500)->get()
            ->map(fn ($l) => [
                'url' => $l->fromPage?->url, 'anchor' => $l->anchor_text,
                'status' => $l->fromPage?->http_status, 'indexable' => (bool) $l->fromPage?->is_indexable,
            ])->filter(fn ($r) => $r['url'])->values()->all();

        $outbound = WebsiteInternalLink::where('from_page_id', $page->id)
            ->where('status', 'discovered')->with('toPage:id,url,http_status,is_indexable')
            ->limit(500)->get()
            ->map(fn ($l) => [
                'url' => $l->toPage?->url, 'anchor' => $l->anchor_text,
                'status' => $l->toPage?->http_status, 'indexable' => (bool) $l->toPage?->is_indexable,
                'broken' => (int) $l->toPage?->http_status >= 400,
            ])->filter(fn ($r) => $r['url'])->values()->all();

        $suggestedInbound = WebsiteInternalLink::where('to_page_id', $page->id)
            ->where('status', 'suggested')->with('fromPage:id,url')
            ->limit(50)->get()
            ->map(fn ($l) => ['url' => $l->fromPage?->url, 'anchor' => $l->anchor_text])
            ->filter(fn ($r) => $r['url'])->values()->all();

        // A crawl that is still running hasn't built the link graph yet, so
        // inbound counts read 0 for every page — don't mislabel those as orphans.
        $crawlRunning = CrawlRun::where('website_id', $websiteId)
            ->where('status', CrawlRun::STATUS_RUNNING)->exists();
        $path = (string) parse_url($page->url, PHP_URL_PATH);
        $isHomepage = $path === '' || $path === '/';

        return [
            'page' => [
                'url' => $page->url,
                'title' => $page->title,
                'http_status' => $page->http_status,
                'is_indexable' => (bool) $page->is_indexable,
                'click_depth' => $page->click_depth,
                'page_score' => $page->page_score,
                'inbound_count' => (int) $page->inbound_link_count,
                'outbound_count' => (int) $page->internal_link_count,
                'is_homepage' => $isHomepage,
                'crawl_running' => $crawlRunning,
                // Discovery provenance — lets the UI explain how a (broken) page was
                // found when no internal link points to it (sitemap / Search Console).
                'source_sitemap' => (bool) $page->source_sitemap,
                'source_gsc' => (bool) $page->source_gsc,
                'discovered_at' => $page->discovered_at,
            ],
            'path' => $this->pathFromHome($websiteId, $page),
            'inbound' => $inbound,
            'outbound' => $outbound,
            'suggested_inbound' => $suggestedInbound,
        ];
    }

    /**
     * Shortest internal-link click-path from the homepage to a page, e.g.
     * [home, /category, /category/sub, /target]. Empty when the page isn't
     * reachable from the homepage by internal links (a disconnected/orphan page).
     *
     * @return array<int,array{url:string,title:?string,is_current:bool}>
     */
    private function pathFromHome(int $websiteId, WebsitePage $page): array
    {
        $parents = $this->bfsParents($websiteId);
        if (! array_key_exists($page->id, $parents)) {
            return [];
        }

        $ids = [];
        $node = $page->id;
        $guard = 0;
        while ($node !== null && $guard++ < 100) {
            $ids[] = $node;
            $node = $parents[$node] ?? null;
        }
        $ids = array_reverse($ids); // home → … → target

        $byId = WebsitePage::whereIn('id', $ids)->pluck('url', 'id');
        $titles = WebsitePage::whereIn('id', $ids)->pluck('title', 'id');

        $out = [];
        foreach ($ids as $id) {
            if (! isset($byId[$id])) {
                continue;
            }
            $out[] = [
                'url' => (string) $byId[$id],
                'title' => $titles[$id] ?? null,
                'is_current' => $id === $page->id,
            ];
        }

        return $out;
    }

    /**
     * BFS parent map {page_id => parent_page_id|null} from the homepage over the
     * discovered internal-link graph. Cached (versioned) so the per-page path
     * lookup is a cheap parent-walk rather than a full graph scan each view.
     *
     * @return array<int,int|null>
     */
    private function bfsParents(int $websiteId): array
    {
        return Cache::remember(
            "ls-parents:{$websiteId}:".ReportCache::version($websiteId),
            3600,
            function () use ($websiteId): array {
                $homeId = $this->homepageId($websiteId);
                if ($homeId === null) {
                    return [];
                }

                // Keyset pagination (lazyById = WHERE id > X), NOT chunk() which
                // uses LIMIT/OFFSET — on million-edge sites the deep offsets make
                // this O(n²) and the Link Structure page stalls for minutes.
                $adjacency = [];
                foreach (
                    DB::table('website_internal_links')
                        ->where('website_id', $websiteId)->where('status', 'discovered')
                        ->select('id', 'from_page_id', 'to_page_id')
                        ->lazyById(5000, 'id') as $r
                ) {
                    $adjacency[$r->from_page_id][] = $r->to_page_id;
                }

                $parent = [$homeId => null];
                $queue = [$homeId];
                while ($queue !== []) {
                    $node = array_shift($queue);
                    foreach ($adjacency[$node] ?? [] as $next) {
                        if (! array_key_exists($next, $parent)) {
                            $parent[$next] = $node;
                            $queue[] = $next;
                        }
                    }
                }

                return $parent;
            }
        );
    }

    private function homepageId(int $websiteId): ?int
    {
        $website = Website::find($websiteId);
        if ($website) {
            $id = WebsitePage::where('website_id', $websiteId)
                ->where('url_hash', WebsitePage::hashUrl('https://'.$website->domain))->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return WebsitePage::where('website_id', $websiteId)
            ->whereNull('removed_at')->orderByRaw('LENGTH(url) asc')->orderBy('id')->value('id');
    }

    /**
     * Crawl knowledge for one URL — for the AI ContextBuilder.
     *
     * @return array<string,mixed>|null
     */
    public function pageIntel(int $websiteId, string $url): ?array
    {
        $page = WebsitePage::where('website_id', $websiteId)
            ->where('url_hash', WebsitePage::hashUrl($url))->first();
        if (! $page) {
            return null;
        }

        $findings = CrawlFinding::where('website_id', $websiteId)->where('page_id', $page->id)
            ->where('status', 'open')->pluck('type')->all();

        return [
            'http_status' => $page->http_status,
            'is_indexable' => (bool) $page->is_indexable,
            'word_count' => $page->word_count,
            'inbound_links' => (int) $page->inbound_link_count,
            'outbound_internal_links' => (int) $page->internal_link_count,
            'click_depth' => $page->click_depth,
            'is_orphan' => (int) $page->inbound_link_count === 0,
            'page_score' => $page->page_score,
            // How this URL was discovered — gives the AI fix brief a starting point
            // for broken pages with no on-site referrer (sitemap / Search Console).
            'discovered_via' => array_values(array_filter([
                (int) $page->inbound_link_count > 0 ? 'internal_links' : null,
                $page->source_sitemap ? 'sitemap' : null,
                $page->source_gsc ? 'search_console' : null,
            ])),
            'issues' => $findings,
        ];
    }

    private function describe(CrawlFinding $f): string
    {
        $d = $f->detail ?? [];

        return match ($f->type) {
            'broken_internal' => 'Internal link → '.($d['http_status'] ?? '4xx').' ('.($d['inbound_links'] ?? 1).' inbound)',
            'broken_external' => 'External link → '.($d['http_status'] ?? 'error'),
            'broken_page' => 'Page returns '.($d['http_status'] ?? '4xx').$this->discoveryOrigin($d),
            'noindex_important' => 'noindex on a page that earns traffic',
            'canonical_mismatch' => 'Canonical points elsewhere',
            'orphan_page' => 'No internal links point here',
            'deep_page' => 'Click depth '.($d['click_depth'] ?? '3+').' from homepage',
            'thin_content' => 'Only '.($d['word_count'] ?? 'few').' words',
            'redirecting_url' => 'Redirects to '.$this->shortUrl((string) ($d['redirect_target'] ?? '')),
            'crawl_blocked' => 'Crawler blocked ('.($d['reason'] ?? 'unknown').')',
            default => ucfirst(str_replace('_', ' ', $f->type)),
        };
    }

    /**
     * Human " · found via …" suffix for a broken page, built from the provenance
     * recorded on the finding (internal referrers / sitemap / Search Console).
     *
     * @param  array<string,mixed>  $d  finding detail
     */
    private function discoveryOrigin(array $d): string
    {
        $parts = [];
        $n = (int) ($d['inbound_internal'] ?? 0);
        if ($n > 0) {
            $parts[] = $n.' internal link'.($n === 1 ? '' : 's');
        }
        if (! empty($d['source_sitemap'])) {
            $parts[] = 'sitemap';
        }
        if (! empty($d['source_gsc'])) {
            $parts[] = 'Search Console';
        }

        return $parts === [] ? '' : ' · found via '.implode(', ', $parts);
    }

    private function shortUrl(?string $url): string
    {
        if (! $url) {
            return '—';
        }
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $path !== '' && $path !== '/' ? $path : $url;
    }
}
