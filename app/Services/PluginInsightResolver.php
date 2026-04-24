<?php

namespace App\Services;

use App\Models\PageAuditReport;
use App\Models\PageIndexingStatus;
use App\Models\RankTrackingKeyword;
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

        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz)->endOfDay();
        $start30 = $end->copy()->subDays(29)->startOfDay();
        $start90 = $end->copy()->subDays(89)->startOfDay();

        $gscTotals30 = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->where('page', $normalized)
            ->whereDate('date', '>=', $start30->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position, AVG(ctr) as ctr')
            ->first();

        $gscTotals90 = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->where('page', $normalized)
            ->whereDate('date', '>=', $start90->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('SUM(clicks) as clicks, SUM(impressions) as impressions')
            ->first();

        $clickSeries = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->where('page', $normalized)
            ->whereDate('date', '>=', $start90->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('DATE(date) as d, SUM(clicks) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn ($r) => ['date' => (string) $r->d, 'clicks' => (int) $r->c])
            ->all();

        $topQueries = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->where('page', $normalized)
            ->whereDate('date', '>=', $start30->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
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
                ->where(function ($q) use ($normalized): void {
                    $q->where('target_url', $normalized)
                        ->orWhere('current_url', $normalized);
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

        $audit = PageAuditReport::query()
            ->where('website_id', $website->id)
            ->where('page_hash', hash('sha256', $normalized))
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
            ->where('page', $normalized)
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
            ->where('page', $normalized)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('query', '!=', '')
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
