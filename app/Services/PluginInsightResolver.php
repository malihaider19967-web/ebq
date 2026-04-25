<?php

namespace App\Services;

use App\Models\KeywordMetric;
use App\Models\PageAuditReport;
use App\Models\PageIndexingStatus;
use App\Models\RankTrackingKeyword;
use App\Models\RankTrackingSnapshot;
use App\Models\SearchConsoleData;
use App\Models\Website;
use Illuminate\Support\Carbon;

/**
 * Assembles the payload the WordPress plugin shows beside a single post:
 * GSC totals, tracked-keyword rank + recent change, cannibalization flag,
 * striking-distance flag, and latest audit performance score.
 *
 * Reuses ReportDataService + SerpFeatureRiskService so nothing duplicates.
 */
class PluginInsightResolver
{
    public function __construct(
        private readonly ReportDataService $reports,
        private readonly SerpFeatureRiskService $serpRisk,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forUrl(Website $website, string $canonicalUrl, ?string $targetKeyword = null, ?string $externalPostId = null): array
    {
        $normalized = trim($canonicalUrl);
        if ($normalized === '' || ! $website->isAuditUrlForThisSite($normalized)) {
            return [
                'ok' => false,
                'error' => 'url_not_for_website',
                'url' => $normalized,
            ];
        }

        // GSC stores URLs as Google indexed them; WP's get_permalink() returns
        // whatever the install thinks is canonical. Match against the full set
        // of trailing-slash / www / scheme variants so a `https://x.com/post/`
        // permalink still matches a `https://x.com/post` row in GSC.
        $variants = $this->pageVariants($normalized);

        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz)->endOfDay();
        $start30 = $end->copy()->subDays(29)->startOfDay();
        $start90 = $end->copy()->subDays(89)->startOfDay();

        $gscTotals30 = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start30->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->tap(fn ($q) => $this->applyPageMatch($q, $normalized))
            ->selectRaw('SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position, AVG(ctr) as ctr')
            ->first();

        $gscTotals90 = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start90->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->tap(fn ($q) => $this->applyPageMatch($q, $normalized))
            ->selectRaw('SUM(clicks) as clicks, SUM(impressions) as impressions')
            ->first();

        $clickSeries = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start90->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->tap(fn ($q) => $this->applyPageMatch($q, $normalized))
            ->selectRaw('DATE(date) as d, SUM(clicks) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn ($r) => ['date' => (string) $r->d, 'clicks' => (int) $r->c])
            ->all();

        $topQueries = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start30->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->tap(fn ($q) => $this->applyPageMatch($q, $normalized))
            ->selectRaw('query, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position')
            ->where('query', '!=', '')
            ->groupBy('query')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'query' => (string) $r->query,
                'clicks' => (int) $r->clicks,
                'impressions' => (int) $r->impressions,
                'position' => $r->position !== null ? round((float) $r->position, 1) : null,
            ])
            ->all();

        $queries = collect($topQueries)->pluck('query')->all();

        $cannibalization = collect($this->reports->cannibalizationReport($website->id, null, null, 500))
            ->filter(fn (array $row) => in_array($row['query'], $queries, true))
            ->values()
            ->take(3)
            ->map(fn (array $row) => [
                'query' => $row['query'],
                'primary_page' => $row['primary_page'],
                'is_primary_this_page' => $row['primary_page'] === $normalized,
                'competing_pages' => array_values(array_filter(
                    $row['competing_pages'],
                    fn (array $p) => $p['page'] !== $normalized,
                )),
            ])
            ->values()
            ->all();

        $striking = collect($this->reports->strikingDistance($website->id, null, null, 200))
            ->filter(fn (array $row) => in_array($row['query'], $queries, true))
            ->values()
            ->take(5)
            ->all();

        $trackedKeyword = null;
        if ($targetKeyword !== null && $targetKeyword !== '') {
            $trackedKeyword = $this->resolveTrackedKeyword($website, $targetKeyword);
        }
        if (! $trackedKeyword) {
            $trackedKeyword = RankTrackingKeyword::query()
                ->where('website_id', $website->id)
                ->where('is_active', true)
                ->where(function ($q) use ($variants): void {
                    $q->whereIn('target_url', $variants)
                        ->orWhereIn('current_url', $variants);
                })
                ->first();
        }

        $trackedPayload = null;
        if ($trackedKeyword) {
            $risk = $this->serpRisk->riskFor($trackedKeyword);
            $trackedPayload = [
                'id' => $trackedKeyword->id,
                'keyword' => $trackedKeyword->keyword,
                'current_position' => $trackedKeyword->current_position,
                'best_position' => $trackedKeyword->best_position,
                'position_change' => $trackedKeyword->position_change,
                'last_checked_at' => $trackedKeyword->last_checked_at?->toIso8601String(),
                'country' => $trackedKeyword->country,
                'device' => $trackedKeyword->device,
                'serp_risk' => $risk,
            ];
        }

        $variantHashes = array_map(static fn (string $v) => hash('sha256', $v), $variants);
        $audit = PageAuditReport::query()
            ->where('website_id', $website->id)
            ->whereIn('page_hash', $variantHashes)
            ->latest('audited_at')
            ->first();

        $auditPayload = null;
        if ($audit) {
            $cwv = is_array($audit->result) ? ($audit->result['core_web_vitals'] ?? []) : [];
            $mobile = is_array($cwv['mobile'] ?? null) ? $cwv['mobile'] : [];
            $desktop = is_array($cwv['desktop'] ?? null) ? $cwv['desktop'] : [];
            $auditPayload = [
                'report_id' => $audit->id,
                'audited_at' => $audit->audited_at?->toIso8601String(),
                'performance_score_mobile' => isset($mobile['performance_score']) ? (int) $mobile['performance_score'] : null,
                'performance_score_desktop' => isset($desktop['performance_score']) ? (int) $desktop['performance_score'] : null,
                'lcp_ms_mobile' => isset($mobile['lcp_ms']) ? (int) $mobile['lcp_ms'] : null,
                'cls_mobile' => isset($mobile['cls']) ? (float) $mobile['cls'] : null,
            ];
        }

        $indexingRow = PageIndexingStatus::query()
            ->where('website_id', $website->id)
            ->tap(fn ($q) => $this->applyPageMatch($q, $normalized))
            ->orderByDesc('last_google_status_checked_at')
            ->first();

        $indexingPayload = null;
        if ($indexingRow) {
            $indexingPayload = [
                'verdict' => $indexingRow->google_verdict,
                'coverage_state' => $indexingRow->google_coverage_state,
                'last_crawl_at' => $indexingRow->google_last_crawl_at?->toIso8601String(),
                'checked_at' => $indexingRow->last_google_status_checked_at?->toIso8601String(),
            ];
        }

        $primaryQuery = isset($topQueries[0]['query']) ? (string) $topQueries[0]['query'] : null;

        return [
            'ok' => true,
            'url' => $normalized,
            'external_post_id' => $externalPostId,
            'gsc' => [
                'totals_30d' => [
                    'clicks' => (int) ($gscTotals30->clicks ?? 0),
                    'impressions' => (int) ($gscTotals30->impressions ?? 0),
                    'position' => $gscTotals30 && $gscTotals30->position !== null ? round((float) $gscTotals30->position, 1) : null,
                    'ctr' => $gscTotals30 && $gscTotals30->ctr !== null ? round((float) $gscTotals30->ctr * 100, 2) : null,
                ],
                'totals_90d' => [
                    'clicks' => (int) ($gscTotals90->clicks ?? 0),
                    'impressions' => (int) ($gscTotals90->impressions ?? 0),
                ],
                'click_series_90d' => $clickSeries,
                'top_queries_30d' => $topQueries,
                'primary_query' => $primaryQuery,
            ],
            'indexing' => $indexingPayload,
            'flags' => [
                'cannibalized' => ! empty($cannibalization),
                'striking_distance' => ! empty($striking),
                'tracked' => $trackedPayload !== null,
            ],
            'cannibalization' => $cannibalization,
            'striking_distance' => $striking,
            'tracked_keyword' => $trackedPayload,
            'audit' => $auditPayload,
            'country_breakdown' => $this->countryBreakdown($website, $normalized, null, 5)['by_country'],
        ];
    }

    /**
     * @param  list<string>  $urls
     * @return array<string, array<string, mixed>>
     */
    public function bulkForUrls(Website $website, array $urls): array
    {
        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz)->endOfDay();
        $start30 = $end->copy()->subDays(29)->startOfDay();

        $filtered = array_values(array_unique(array_filter(
            array_map('trim', $urls),
            fn (string $u) => $u !== '' && $website->isAuditUrlForThisSite($u),
        )));

        if (empty($filtered)) {
            return [];
        }

        $gsc = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereIn('page', $filtered)
            ->whereDate('date', '>=', $start30->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('page, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position')
            ->groupBy('page')
            ->get()
            ->keyBy('page');

        $cannibalized = collect($this->reports->cannibalizationReport($website->id, null, null, 500))
            ->flatMap(fn (array $row) => [$row['primary_page'] => true] + collect($row['competing_pages'])->mapWithKeys(fn (array $p) => [$p['page'] => true])->all())
            ->keys()
            ->flip();

        $out = [];
        foreach ($filtered as $url) {
            $g = $gsc->get($url);
            $out[$url] = [
                'clicks_30d' => $g ? (int) $g->clicks : 0,
                'impressions_30d' => $g ? (int) $g->impressions : 0,
                'avg_position' => $g && $g->position !== null ? round((float) $g->position, 1) : null,
                'flags' => [
                    'cannibalized' => isset($cannibalized[$url]),
                    'tracked' => RankTrackingKeyword::query()
                        ->where('website_id', $website->id)
                        ->where(function ($q) use ($url): void {
                            $q->where('target_url', $url)->orWhere('current_url', $url);
                        })
                        ->exists(),
                ],
            ];
        }

        return $out;
    }

    private function resolveTrackedKeyword(Website $website, string $keyword): ?RankTrackingKeyword
    {
        return RankTrackingKeyword::query()
            ->where('website_id', $website->id)
            ->whereRaw('LOWER(keyword) = ?', [mb_strtolower(trim($keyword))])
            ->first();
    }

    /**
     * Ranked focus-keyword candidates for a URL, ordered by opportunity score
     * (same formula as the striking-distance report). The plugin uses this to
     * fill a dropdown in the editor rather than asking the user to type one.
     *
     * @return list<array{query: string, impressions: int, clicks: int, position: float|null, ctr: float|null, opportunity_score: float, tracked: bool}>
     */
    public function focusKeywordSuggestions(Website $website, string $canonicalUrl, int $limit = 10): array
    {
        $normalized = trim($canonicalUrl);
        if ($normalized === '' || ! $website->isAuditUrlForThisSite($normalized)) {
            return [];
        }

        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz)->endOfDay();
        $start = $end->copy()->subDays(89)->startOfDay();

        $rows = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('query', '!=', '')
            ->tap(fn ($q) => $this->applyPageMatch($q, $normalized))
            ->selectRaw('query, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position, AVG(ctr) as ctr')
            ->groupBy('query')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $trackedQueries = RankTrackingKeyword::query()
            ->where('website_id', $website->id)
            ->pluck('keyword')
            ->map(fn ($k) => mb_strtolower(trim((string) $k)))
            ->flip();

        $out = $rows->map(function ($r) use ($trackedQueries) {
            $impressions = (int) $r->impressions;
            $clicks = (int) $r->clicks;
            $pos = $r->position !== null ? round((float) $r->position, 1) : null;
            $ctr = $r->ctr !== null ? round((float) $r->ctr * 100, 2) : null;
            $score = ($impressions / 100) + ($pos !== null ? max(0, 20 - $pos) : 0) - (($ctr ?? 0) * 0.6);

            return [
                'query' => (string) $r->query,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'position' => $pos,
                'ctr' => $ctr,
                'opportunity_score' => round($score, 1),
                'tracked' => isset($trackedQueries[mb_strtolower((string) $r->query)]),
            ];
        })
            ->sortByDesc('opportunity_score')
            ->take($limit)
            ->values()
            ->all();

        return $out;
    }

    /**
     * Actual competitor SERP for a query on this website's market — pulled
     * from the latest RankTrackingSnapshot.top_results for a matching tracked
     * keyword. Used by the editor to render a real-competitor SERP panel.
     *
     * @return array{matched: bool, query: string, checked_at: ?string, results: list<array<string, mixed>>}
     */
    public function serpPreview(Website $website, string $query, int $limit = 5): array
    {
        $q = trim($query);
        if ($q === '') {
            return ['matched' => false, 'query' => $q, 'checked_at' => null, 'results' => []];
        }

        $keyword = RankTrackingKeyword::query()
            ->where('website_id', $website->id)
            ->whereRaw('LOWER(keyword) = ?', [mb_strtolower($q)])
            ->first();

        if (! $keyword) {
            return ['matched' => false, 'query' => $q, 'checked_at' => null, 'results' => []];
        }

        $snapshot = \App\Models\RankTrackingSnapshot::query()
            ->where('rank_tracking_keyword_id', $keyword->id)
            ->where('status', 'ok')
            ->orderByDesc('checked_at')
            ->first();

        if (! $snapshot) {
            return ['matched' => true, 'query' => $q, 'checked_at' => null, 'results' => []];
        }

        $top = is_array($snapshot->top_results) ? $snapshot->top_results : [];
        $results = [];
        foreach (array_slice($top, 0, $limit) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $results[] = [
                'position' => isset($row['position']) ? (int) $row['position'] : null,
                'title' => isset($row['title']) ? (string) $row['title'] : '',
                'url' => isset($row['link']) ? (string) $row['link'] : (isset($row['url']) ? (string) $row['url'] : ''),
                'snippet' => isset($row['snippet']) ? (string) $row['snippet'] : '',
            ];
        }

        return [
            'matched' => true,
            'query' => $q,
            'checked_at' => $snapshot->checked_at?->toIso8601String(),
            'results' => $results,
        ];
    }

    /**
     * Related keyphrase suggestions for the focus keyword — Yoast Premium's
     * "related keyphrases" without Semrush. Three signal layers, merged and
     * de-duped, so we don't burn an external credit per writer keystroke:
     *
     *   1. Site-wide GSC queries containing the focus tokens, last 90 days
     *      (filters out the post's own URL so we don't suggest queries it
     *       already targets).
     *   2. Related searches + People-Also-Ask harvested from any
     *      RankTrackingSnapshot the platform has run for matching keywords.
     *   3. KE search-volume from the local cache (free — no API call).
     *
     * Each suggestion ships with `volume`, `clicks`, `impressions`,
     * `position`, `source` (gsc | related | paa) and `score`.
     *
     * @return list<array<string, mixed>>
     */
    public function relatedKeywords(
        Website $website,
        string $focusKeyword,
        ?string $excludeUrl = null,
        int $limit = 20,
    ): array {
        $focus = trim($focusKeyword);
        if ($focus === '') {
            return [];
        }
        $tokens = $this->significantTokens($focus);
        if ($tokens === []) {
            return [];
        }
        $focusLower = mb_strtolower($focus);

        $excludeVariants = $excludeUrl !== null && trim($excludeUrl) !== ''
            ? $this->pageVariants($excludeUrl)
            : [];

        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz)->endOfDay();
        $start = $end->copy()->subDays(89)->startOfDay();

        // ─── 1. GSC queries on this site that contain the focus tokens ─────
        $gscRows = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('query', '!=', '')
            ->when($excludeVariants !== [], fn ($q) => $q->whereNotIn('page', $excludeVariants))
            ->where(function ($w) use ($tokens) {
                foreach ($tokens as $tok) {
                    $w->orWhere('query', 'LIKE', '%'.$tok.'%');
                }
            })
            ->selectRaw('query,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                AVG(position) AS position')
            ->groupBy('query')
            ->orderByDesc('impressions')
            ->limit(150)
            ->get();

        $bag = []; // keyword(lower) → row

        foreach ($gscRows as $r) {
            $kw = trim((string) $r->query);
            if ($kw === '') continue;
            $kwLower = mb_strtolower($kw);
            if ($kwLower === $focusLower) continue; // not "related" to itself

            $matched = 0;
            foreach ($tokens as $tok) {
                if (str_contains($kwLower, $tok)) $matched++;
            }
            if ($matched === 0) continue;

            $impressions = (int) $r->impressions;
            $clicks = (int) $r->clicks;
            $position = $r->position !== null ? round((float) $r->position, 1) : null;

            $bag[$kwLower] = [
                'keyword' => $kw,
                'source' => 'gsc',
                'impressions' => $impressions,
                'clicks' => $clicks,
                'position' => $position,
                'volume' => null,
                'score' => ($impressions / 100)
                    + ($matched * 5)
                    + ($position !== null ? max(0, 20 - $position) : 0),
            ];
        }

        // ─── 2. Related searches + PAA from rank-tracker snapshots ─────────
        // Find tracked keywords that look related (token overlap) and pull
        // related/PAA arrays from their most-recent snapshot. Caps the join
        // to 5 tracked keywords so this stays a cheap query.
        $relatedKeywordIds = RankTrackingKeyword::query()
            ->where('website_id', $website->id)
            ->where('is_active', true)
            ->where(function ($w) use ($tokens) {
                foreach ($tokens as $tok) {
                    $w->orWhereRaw('LOWER(keyword) LIKE ?', ['%'.$tok.'%']);
                }
            })
            ->orderByDesc('last_checked_at')
            ->limit(5)
            ->pluck('id');

        if ($relatedKeywordIds->isNotEmpty()) {
            $snapshots = RankTrackingSnapshot::query()
                ->whereIn('rank_tracking_keyword_id', $relatedKeywordIds)
                ->where('status', 'ok')
                ->orderByDesc('checked_at')
                ->limit(20)
                ->get(['related_searches', 'people_also_ask']);

            foreach ($snapshots as $snap) {
                foreach ([
                    ['related_searches', 'related'],
                    ['people_also_ask',  'paa'],
                ] as [$column, $sourceTag]) {
                    $list = is_array($snap->{$column}) ? $snap->{$column} : [];
                    foreach ($list as $item) {
                        $kw = '';
                        if (is_string($item)) {
                            $kw = trim($item);
                        } elseif (is_array($item)) {
                            $kw = trim((string) ($item['query'] ?? $item['question'] ?? $item['keyword'] ?? ''));
                        }
                        if ($kw === '') continue;
                        $kwLower = mb_strtolower($kw);
                        if ($kwLower === $focusLower) continue;
                        if (isset($bag[$kwLower])) continue; // GSC row wins (it has volume/clicks)

                        $matched = 0;
                        foreach ($tokens as $tok) {
                            if (str_contains($kwLower, $tok)) $matched++;
                        }

                        $bag[$kwLower] = [
                            'keyword' => $kw,
                            'source' => $sourceTag,
                            'impressions' => 0,
                            'clicks' => 0,
                            'position' => null,
                            'volume' => null,
                            'score' => 5 + ($matched * 4),
                        ];
                    }
                }
            }
        }

        if ($bag === []) {
            return [];
        }

        // ─── 3. Search volume from KE cache (free — no API call) ───────────
        $hashes = array_map([KeywordMetric::class, 'hashKeyword'], array_keys($bag));
        $metrics = KeywordMetric::query()
            ->whereIn('keyword_hash', $hashes)
            ->where('country', 'global')
            ->get(['keyword_hash', 'search_volume']);
        $volumeByHash = $metrics->pluck('search_volume', 'keyword_hash')->all();

        foreach ($bag as $kwLower => &$row) {
            $hash = KeywordMetric::hashKeyword($kwLower);
            $vol = $volumeByHash[$hash] ?? null;
            if ($vol !== null) {
                $row['volume'] = (int) $vol;
                // Volume is a strong second-order ranker — boost score so
                // higher-volume related keywords float to the top.
                $row['score'] += min(20, $vol > 0 ? log10($vol) * 4 : 0);
            }
        }
        unset($row);

        $out = array_values($bag);
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_map(static fn (array $r) => [
            'keyword' => $r['keyword'],
            'source' => $r['source'],
            'volume' => $r['volume'],
            'impressions' => $r['impressions'],
            'clicks' => $r['clicks'],
            'position' => $r['position'],
            'score' => round($r['score'], 1),
        ], $out), 0, $limit);
    }

    /**
     * Suggest other URLs on this website worth linking *to* from the post
     * being edited. Uses a much stronger signal than Yoast's word-overlap
     * approach: actual Search Console performance.
     *
     * Algorithm:
     *   1. Tokenise the focus keyword (or title fallback) into significant
     *      words (>=3 chars, no stopwords).
     *   2. Find SearchConsoleData rows on this website, last 90 days, where
     *      `page` is not the current URL AND any token appears in `query`.
     *   3. Group by (page, query); score each row as
     *         impressions/100 + matched_tokens * 5 + (20 - position).
     *   4. Per page, keep the highest-scoring query.
     *   5. Return top N pages.
     *
     * Returns each suggestion with the matching query so the plugin can
     * render "Ranks #4 for 'best running shoes' (1.2k impr/mo)".
     *
     * @return list<array{url: string, top_query: string, position: ?float, impressions: int, clicks: int, score: float, match_type: string}>
     */
    public function internalLinkSuggestions(
        Website $website,
        string $canonicalUrl,
        ?string $focusKeyword = null,
        ?string $postTitle = null,
        int $limit = 8,
    ): array {
        $normalized = trim($canonicalUrl);
        if ($normalized === '' || ! $website->isAuditUrlForThisSite($normalized)) {
            return [];
        }

        $sourceText = trim((string) ($focusKeyword ?: $postTitle ?: ''));
        if ($sourceText === '') {
            return [];
        }
        $matchType = ($focusKeyword !== null && trim($focusKeyword) !== '') ? 'focus_keyword' : 'title_overlap';

        $tokens = $this->significantTokens($sourceText);
        if (empty($tokens)) {
            return [];
        }

        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz)->endOfDay();
        $start = $end->copy()->subDays(89)->startOfDay();

        $variants = $this->pageVariants($normalized);

        $query = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereNotIn('page', $variants)
            ->whereNotNull('page')
            ->where('page', '!=', '')
            ->where('query', '!=', '')
            ->where(function ($w) use ($tokens) {
                foreach ($tokens as $tok) {
                    $w->orWhere('query', 'LIKE', '%'.$tok.'%');
                }
            })
            ->selectRaw('page, query, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position')
            ->groupBy('page', 'query')
            ->orderByDesc('impressions')
            ->limit(200);

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [];
        }

        // Keep the highest-scoring (page, query) per page.
        $perPage = [];
        foreach ($rows as $r) {
            $page = (string) $r->page;
            $q = mb_strtolower((string) $r->query);
            $matched = 0;
            foreach ($tokens as $tok) {
                if (str_contains($q, $tok)) {
                    $matched++;
                }
            }
            if ($matched === 0) {
                continue;
            }

            $impressions = (int) $r->impressions;
            $clicks = (int) $r->clicks;
            $position = $r->position !== null ? round((float) $r->position, 1) : null;

            $score = ($impressions / 100)
                + ($matched * 5)
                + ($position !== null ? max(0, 20 - $position) : 0);

            if (! isset($perPage[$page]) || $perPage[$page]['score'] < $score) {
                $perPage[$page] = [
                    'url' => $page,
                    'top_query' => (string) $r->query,
                    'position' => $position,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'score' => round($score, 2),
                    'match_type' => $matchType,
                ];
            }
        }

        usort($perPage, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_values($perPage), 0, $limit);
    }

    /**
     * All reasonable variants of a page URL, used to bridge mismatches
     * between WP's `get_permalink()` (whatever the install thinks is canonical)
     * and what Google Search Console actually stored.
     *
     * Generates the cross-product of {trailing slash on/off} × {www on/off} ×
     * {http, https}, keeping the original URL first. Up to 12 variants.
     *
     * @return list<string>
     */
    /** Public probe used only by the controller's diagnostic block. */
    public function __publicPageVariants(string $url): array
    {
        return $this->pageVariants($url);
    }

    private function pageVariants(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return [$url];
        }
        $host = strtolower((string) $parts['host']);
        $hostNoWww = preg_replace('/^www\./', '', $host) ?: $host;
        $hostWww = 'www.'.$hostNoWww;
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }

        // Important: for root path we need BOTH `/` and `` (empty) so
        // `https://example.com` and `https://example.com/` both appear in
        // the variant set. The previous logic only ever emitted `/` for
        // root, so the normalizer's no-trailing-slash storage form (e.g.
        // `https://example.com`) was never matched on the homepage.
        if ($path === '/') {
            $paths = ['', '/'];
        } else {
            $pathNoSlash = rtrim($path, '/');
            $paths = [$pathNoSlash, $pathNoSlash.'/'];
        }
        $query = ! empty($parts['query']) ? '?'.$parts['query'] : '';

        $hosts = [$hostNoWww, $hostWww];
        $schemes = ['https', 'http'];

        // Include the original URL plus its lowercased twin — UrlNormalizer
        // lowercases the whole URL on insert, so even an uppercase slug
        // (e.g. `/My-Post/`) needs the lowercase variant to hit storage.
        $variants = [$url, mb_strtolower($url)];
        foreach ($schemes as $scheme) {
            foreach ($hosts as $h) {
                foreach ($paths as $p) {
                    $variants[] = $scheme.'://'.$h.$p.$query;
                    $variants[] = mb_strtolower($scheme.'://'.$h.$p.$query);
                }
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * Apply the page-match clause to a SearchConsoleData (or PageIndexingStatus)
     * query.
     *
     * Strategy:
     *   1. Strict — `whereIn` over the 12 cheap variants (cross of
     *      scheme/www/trailing-slash). Hits an index, fast.
     *   2. Host-anchored LIKE fallback for the realistic GSC mismatches we
     *      can't enumerate exactly: query strings, AMP, CDN rewrites,
     *      Google's normalisation drift.
     *
     * The LIKE patterns are anchored on `://host` (and `://www.host`) so they
     * never spill across websites — `LIKE '%://example.com/post'` cannot
     * match `evil-example.com` because the `://` is required before the host.
     */
    private function applyPageMatch(\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query, string $url): void
    {
        $variants = $this->pageVariants($url);
        $parts = parse_url($url);
        $host  = strtolower((string) ($parts['host'] ?? ''));
        $hostNoWww = preg_replace('/^www\./', '', $host) ?: $host;
        $path = (string) ($parts['path'] ?? '/');
        $pathNoSlash = $path === '/' ? '' : rtrim($path, '/');

        $query->where(function ($q) use ($variants, $hostNoWww, $pathNoSlash) {
            $q->whereIn('page', $variants);

            if ($hostNoWww === '') {
                return;
            }

            // Build `://host` and `://www.host` host fragments.
            $hostFragments = [
                '%://'.addcslashes($hostNoWww, '\\%_'),
                '%://www.'.addcslashes($hostNoWww, '\\%_'),
            ];
            $pathPart = $pathNoSlash !== '' ? addcslashes($pathNoSlash, '\\%_') : '';

            foreach ($hostFragments as $hf) {
                $bare = $hf.$pathPart;          // …host/path  (or …host)
                $slash = $bare.'/';             // …host/path/ (or …host/)
                $q->orWhere('page', 'LIKE', $bare)
                  ->orWhere('page', 'LIKE', $slash)
                  ->orWhere('page', 'LIKE', $bare.'?%')   // …host/path?utm_*
                  ->orWhere('page', 'LIKE', $slash.'?%')  // …host/path/?utm_*
                  ->orWhere('page', 'LIKE', $bare.'#%')   // …host/path#frag
                  ->orWhere('page', 'LIKE', $slash.'#%'); // …host/path/#frag
            }
        });
    }

    /**
     * Lowercase, drop stopwords, keep tokens >= 3 chars. Returns up to 6 of
     * the longest matches (longer tokens carry more signal).
     *
     * @return list<string>
     */
    private function significantTokens(string $text): array
    {
        static $stop = [
            'the','a','an','and','or','but','of','to','for','in','on','at','by','it','its',
            'as','is','be','are','was','were','this','that','these','those','with','from',
            'what','how','why','when','who','where','which','i','you','we','they','my',
            'your','our','their','can','will','do','does','did','have','has','had','not',
            'no','yes','if','so','than','then','just','about','into','out','up','down',
            'over','under','again','more','most','some','any','all','each','every','one',
        ];

        $lower = mb_strtolower($text);
        // Replace anything not letter/number/space with space, collapse spaces.
        $lower = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $lower);
        $parts = preg_split('/\s+/u', trim($lower));
        $out = [];
        foreach ($parts as $p) {
            if (mb_strlen($p) < 3) continue;
            if (in_array($p, $stop, true)) continue;
            $out[] = $p;
        }

        usort($out, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_values(array_unique(array_slice($out, 0, 6)));
    }

    /**
     * Per-country breakdown for a single post URL (optionally filtered to a
     * specific query). Used by the Gutenberg panel's "Per-country breakdown"
     * section and by the meta box's "Top 3 countries" block.
     *
     * @return array{url: string, query: ?string, by_country: list<array{country: string, clicks: int, impressions: int, ctr: float|null, position: float|null}>}
     */
    public function countryBreakdown(Website $website, string $canonicalUrl, ?string $query = null, int $limit = 10): array
    {
        $normalized = trim($canonicalUrl);
        if ($normalized === '' || ! $website->isAuditUrlForThisSite($normalized)) {
            return ['url' => $normalized, 'query' => $query, 'by_country' => []];
        }

        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz)->endOfDay();
        $start = $end->copy()->subDays(29)->startOfDay();

        $q = $query !== null ? trim($query) : null;

        $rows = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->where('page', $normalized)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('country', '!=', '')
            ->when($q !== null && $q !== '', fn ($builder) => $builder->whereRaw('LOWER(`query`) = ?', [mb_strtolower($q)]))
            ->selectRaw('country, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(ctr) as ctr, AVG(position) as position')
            ->groupBy('country')
            ->orderByDesc('clicks')
            ->limit($limit)
            ->get();

        $out = $rows->map(fn ($r) => [
            'country' => (string) $r->country,
            'clicks' => (int) $r->clicks,
            'impressions' => (int) $r->impressions,
            'ctr' => $r->ctr !== null ? round((float) $r->ctr * 100, 2) : null,
            'position' => $r->position !== null ? round((float) $r->position, 1) : null,
        ])->values()->all();

        return [
            'url' => $normalized,
            'query' => $q,
            'by_country' => $out,
        ];
    }

    /**
     * Presentation-shaped per-country breakdown for the audit report (web,
     * download, and email share the same enrichment). Rows are sorted by
     * clicks desc and include flag, display name, share %, and bar width %.
     *
     * @return array{rows: list<array<string, mixed>>, total_clicks: int, total_impressions: int, max_clicks: int}
     */
    public function countryBreakdownForAuditReport(Website $website, string $canonicalUrl, int $limit = 10): array
    {
        $raw = $this->countryBreakdown($website, $canonicalUrl, null, $limit)['by_country'];
        $totalClicks = array_sum(array_map(fn ($r) => (int) $r['clicks'], $raw));
        $totalImpr = array_sum(array_map(fn ($r) => (int) $r['impressions'], $raw));
        $maxClicks = max(1, (int) (collect($raw)->max('clicks') ?? 0));

        $rows = [];
        foreach ($raw as $r) {
            $name = \App\Support\Countries::name((string) $r['country']);
            $hover = $name.' · '.number_format((int) $r['impressions']).' impressions';
            if ($r['position'] !== null) {
                $hover .= ' · avg position '.$r['position'];
            }
            $rows[] = $r + [
                'name' => $name,
                'flag' => \App\Support\Countries::flag((string) $r['country']),
                'width_pct' => max(2, (int) round(((int) $r['clicks'] / $maxClicks) * 100)),
                'share_pct' => $totalClicks > 0 ? round(((int) $r['clicks'] / $totalClicks) * 100, 1) : 0.0,
                'hover_title' => $hover,
            ];
        }

        return [
            'rows' => $rows,
            'total_clicks' => $totalClicks,
            'total_impressions' => $totalImpr,
            'max_clicks' => $maxClicks,
        ];
    }
}
