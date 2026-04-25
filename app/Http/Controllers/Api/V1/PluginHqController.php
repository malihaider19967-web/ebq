<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PageIndexingStatus;
use App\Models\RankTrackingKeyword;
use App\Models\SearchConsoleData;
use App\Models\Website;
use App\Services\ReportDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "EBQ HQ" admin-page API. Powers the top-level WordPress admin menu the
 * plugin renders — overview, SEO performance, keywords, pages, index status,
 * and the insights opportunity feed (cannibalizations / striking distance /
 * content decay / indexing fails / quick wins).
 *
 * Every method runs under the existing `website.api:read:insights` middleware
 * (per-website Sanctum token) and reuses the same service layer that powers
 * the EBQ.io Livewire dashboards. No new domain logic — this is the API
 * surface, not a parallel implementation.
 */
class PluginHqController extends Controller
{
    public function __construct(private readonly ReportDataService $reports)
    {
    }

    /**
     * Top-level KPIs + insight counts. Used by the HQ Overview tab.
     *   GET /api/v1/hq/overview?range=30d
     */
    public function overview(Request $request): JsonResponse
    {
        $website = $this->website($request);
        [$start, $end, $prevStart, $prevEnd, $rangeKey] = $this->resolveRange($request);

        $current = $this->aggregateGsc($website->id, $start, $end);
        $previous = $this->aggregateGsc($website->id, $prevStart, $prevEnd);

        $rankingKeywords = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereBetween('date', [$start, $end])
            ->where('position', '<=', 100)
            ->where('query', '!=', '')
            ->distinct('query')
            ->count('query');

        $trackedKeywords = RankTrackingKeyword::query()
            ->where('website_id', $website->id)
            ->where('is_active', true)
            ->count();

        $positionSlabs = $this->positionDistribution($website->id, $start, $end);

        // Light line-chart data so the overview can render its own sparkline
        // without a follow-up fetch.
        $sparkline = $this->dailyClicks($website->id, $start, $end);

        return response()->json([
            'website_id' => $website->id,
            'domain' => $website->domain,
            'range' => [
                'key' => $rangeKey,
                'start' => $start,
                'end' => $end,
                'prev_start' => $prevStart,
                'prev_end' => $prevEnd,
            ],
            'kpi' => [
                'clicks' => $this->kpiTriple($current['clicks'], $previous['clicks']),
                'impressions' => $this->kpiTriple($current['impressions'], $previous['impressions']),
                'avg_position' => $this->kpiTriple(
                    $current['avg_position'],
                    $previous['avg_position'],
                    invert: true,
                    decimals: 1,
                ),
                'ctr' => $this->kpiTriple(
                    $current['ctr'] * 100,
                    $previous['ctr'] * 100,
                    decimals: 2,
                    suffix: '%',
                ),
                'ranking_keywords' => $this->kpiTriple($rankingKeywords, null),
                'tracked_keywords' => $this->kpiTriple($trackedKeywords, null),
            ],
            'position_distribution' => $positionSlabs,
            'sparkline' => $sparkline,
            'insight_counts' => $this->reports->insightCounts($website->id),
            'top_winning_keywords' => $this->topMovers($website->id, $start, $end, 'gainers', 5),
            'top_losing_keywords' => $this->topMovers($website->id, $start, $end, 'losers', 5),
        ]);
    }

    /**
     * Time-series for the SEO Performance line chart.
     *   GET /api/v1/hq/performance?range=30d
     */
    public function performance(Request $request): JsonResponse
    {
        $website = $this->website($request);
        [$start, $end, , , $rangeKey] = $this->resolveRange($request);

        $rows = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('date, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS position')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'position' => $row->position !== null ? round((float) $row->position, 2) : null,
                'ctr' => $row->impressions > 0 ? round(($row->clicks / $row->impressions) * 100, 2) : 0.0,
            ])
            ->all();

        return response()->json([
            'range' => ['key' => $rangeKey, 'start' => $start, 'end' => $end],
            'series' => $rows,
        ]);
    }

    /**
     * Tracked keywords list with current position, change, and 30-day GSC
     * metrics for each. Sortable columns map to the table headers in the WP
     * admin UI.
     *   GET /api/v1/hq/keywords?sort=current_position&dir=asc&page=1&search=
     */
    public function keywords(Request $request): JsonResponse
    {
        $website = $this->website($request);
        $sort = $request->query('sort', 'current_position');
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));
        $search = trim((string) $request->query('search', ''));

        $allowedSorts = ['keyword', 'current_position', 'best_position', 'position_change', 'last_checked_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'current_position';
        }

        $query = RankTrackingKeyword::query()
            ->where('website_id', $website->id)
            ->where('is_active', true);

        if ($search !== '') {
            $query->where('keyword', 'LIKE', '%' . addcslashes($search, '\\%_') . '%');
        }

        // current_position can be null when never checked — push nulls to the
        // bottom regardless of asc/desc so the table is always useful.
        if ($sort === 'current_position' || $sort === 'best_position' || $sort === 'position_change') {
            $query->orderByRaw("$sort IS NULL");
            $query->orderBy($sort, $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        $paginator = $query->paginate($perPage);

        $tz = config('app.timezone');
        $rangeEnd = Carbon::yesterday($tz)->toDateString();
        $rangeStart = Carbon::yesterday($tz)->subDays(29)->toDateString();

        $keywords = collect($paginator->items())->pluck('keyword')->map(fn ($k) => mb_strtolower($k))->all();
        $gsc = collect();
        if (! empty($keywords)) {
            $gsc = SearchConsoleData::query()
                ->where('website_id', $website->id)
                ->whereBetween('date', [$rangeStart, $rangeEnd])
                ->whereIn('query', $keywords)
                ->selectRaw('LOWER(`query`) AS qkey, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS position')
                ->groupBy('qkey')
                ->get()
                ->keyBy('qkey');
        }

        $items = collect($paginator->items())->map(function (RankTrackingKeyword $k) use ($gsc) {
            $key = mb_strtolower($k->keyword);
            $g = $gsc->get($key);
            return [
                'id' => $k->id,
                'keyword' => $k->keyword,
                'target_url' => $k->target_url,
                'current_position' => $k->current_position !== null ? (float) $k->current_position : null,
                'best_position' => $k->best_position !== null ? (float) $k->best_position : null,
                'position_change' => $k->position_change !== null ? (float) $k->position_change : null,
                'current_url' => $k->current_url,
                'last_checked_at' => $k->last_checked_at?->toIso8601String(),
                'gsc_clicks' => $g ? (int) $g->clicks : 0,
                'gsc_impressions' => $g ? (int) $g->impressions : 0,
                'gsc_position' => $g ? round((float) $g->position, 2) : null,
                'tags' => $k->tags ?? [],
            ];
        });

        return response()->json([
            'data' => $items->values()->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    /**
     * 30-day position history for a single tracked keyword.
     *   GET /api/v1/hq/keywords/{id}/history
     */
    public function keywordHistory(Request $request, int $id): JsonResponse
    {
        $website = $this->website($request);
        $keyword = RankTrackingKeyword::query()
            ->where('website_id', $website->id)
            ->findOrFail($id);

        $since = Carbon::now()->subDays(90)->toDateTimeString();
        $snapshots = $keyword->snapshots()
            ->where('checked_at', '>=', $since)
            ->orderBy('checked_at')
            ->get(['checked_at', 'position', 'url']);

        return response()->json([
            'keyword' => $keyword->keyword,
            'series' => $snapshots->map(fn ($s) => [
                'at' => $s->checked_at?->toIso8601String(),
                'position' => $s->position !== null ? (float) $s->position : null,
                'url' => $s->url,
            ])->all(),
        ]);
    }

    /**
     * Site-pages directory with per-page GSC aggregates over the chosen range.
     *   GET /api/v1/hq/pages?range=30d&sort=clicks&dir=desc&page=1&search=
     */
    public function pages(Request $request): JsonResponse
    {
        $website = $this->website($request);
        [$start, $end, , , $rangeKey] = $this->resolveRange($request);

        $sort = (string) $request->query('sort', 'clicks');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));

        $allowedSorts = ['clicks', 'impressions', 'position', 'ctr'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'clicks';
        }

        $base = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereBetween('date', [$start, $end])
            ->where('page', '!=', '');

        if ($search !== '') {
            $base->where('page', 'LIKE', '%' . addcslashes($search, '\\%_') . '%');
        }

        $total = (clone $base)->distinct('page')->count('page');

        $rows = (clone $base)
            ->selectRaw('page, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS position')
            ->groupBy('page')
            ->orderByRaw($sort === 'ctr' ? '(SUM(clicks) / NULLIF(SUM(impressions), 0)) ' . $dir : "$sort $dir")
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get()
            ->map(fn ($row) => [
                'page' => (string) $row->page,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'position' => $row->position !== null ? round((float) $row->position, 2) : null,
                'ctr' => $row->impressions > 0 ? round(($row->clicks / $row->impressions) * 100, 2) : 0.0,
            ])
            ->all();

        return response()->json([
            'range' => ['key' => $rangeKey, 'start' => $start, 'end' => $end],
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    /**
     * Index status directory backed by the URL Inspection cache.
     *   GET /api/v1/hq/index-status?status=PASS|PARTIAL|FAIL&page=1
     */
    public function indexStatus(Request $request): JsonResponse
    {
        $website = $this->website($request);
        $status = strtoupper((string) $request->query('status', ''));
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));

        $query = PageIndexingStatus::query()
            ->where('website_id', $website->id);

        if (in_array($status, ['PASS', 'PARTIAL', 'FAIL', 'NEUTRAL'], true)) {
            $query->where('google_verdict', $status);
        }

        if ($search !== '') {
            $query->where('page', 'LIKE', '%' . addcslashes($search, '\\%_') . '%');
        }

        $total = (clone $query)->count();
        $rows = (clone $query)
            ->orderByDesc('last_google_status_checked_at')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        $verdictCounts = PageIndexingStatus::query()
            ->where('website_id', $website->id)
            ->selectRaw('google_verdict, COUNT(*) AS n')
            ->groupBy('google_verdict')
            ->pluck('n', 'google_verdict')
            ->all();

        return response()->json([
            'verdict_counts' => [
                'PASS' => (int) ($verdictCounts['PASS'] ?? 0),
                'PARTIAL' => (int) ($verdictCounts['PARTIAL'] ?? 0),
                'FAIL' => (int) ($verdictCounts['FAIL'] ?? 0),
                'NEUTRAL' => (int) ($verdictCounts['NEUTRAL'] ?? 0),
                'UNKNOWN' => (int) ($verdictCounts[''] ?? 0) + (int) ($verdictCounts[null] ?? 0),
            ],
            'data' => $rows->map(fn (PageIndexingStatus $r) => [
                'page' => $r->page,
                'verdict' => $r->google_verdict,
                'coverage_state' => $r->google_coverage_state,
                'indexing_state' => $r->google_indexing_state,
                'last_crawl_at' => $r->google_last_crawl_at?->toIso8601String(),
                'last_checked_at' => $r->last_google_status_checked_at?->toIso8601String(),
                'last_reindex_requested_at' => $r->last_reindex_requested_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'status' => $status ?: null,
            ],
        ]);
    }

    /**
     * Opportunity feed — cannibalizations, striking-distance keywords, content
     * decay, indexing fails with traffic, and quick wins. Each `type` returns
     * up to 50 rows; the WP UI renders a tab per type.
     *   GET /api/v1/hq/insights/{type}?limit=50
     */
    public function insights(Request $request, string $type): JsonResponse
    {
        $website = $this->website($request);
        $limit = max(5, min(100, (int) $request->query('limit', 25)));

        $payload = match ($type) {
            'cannibalization' => ['rows' => $this->reports->cannibalizationReport($website->id, null, null, $limit)],
            'striking' => ['rows' => $this->reports->strikingDistance($website->id, null, null, $limit)],
            'decay' => $this->reports->contentDecay($website->id, $limit),
            'index_fails' => ['rows' => $this->reports->indexingFailsWithTraffic($website->id, 14, $limit)],
            'quick_wins' => ['rows' => $this->reports->quickWins($website->id, $limit)],
            default => abort(404),
        };

        return response()->json([
            'type' => $type,
            'website_domain' => $website->domain,
            'payload' => $payload,
        ]);
    }

    /* ─── Helpers ──────────────────────────────────────────── */

    private function website(Request $request): Website
    {
        $w = $request->attributes->get('api_website');
        abort_unless($w instanceof Website, 500, 'Website context missing');
        return $w;
    }

    /**
     * Returns [$start, $end, $prevStart, $prevEnd, $rangeKey] as date strings.
     */
    private function resolveRange(Request $request): array
    {
        $key = (string) $request->query('range', '30d');
        $days = match ($key) {
            '7d' => 7,
            '90d' => 90,
            '180d' => 180,
            default => 30,
        };
        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz);
        $start = $end->copy()->subDays($days - 1);
        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1);

        return [
            $start->toDateString(),
            $end->toDateString(),
            $prevStart->toDateString(),
            $prevEnd->toDateString(),
            $key === '7d' || $key === '30d' || $key === '90d' || $key === '180d' ? $key : '30d',
        ];
    }

    /**
     * @return array{clicks:int, impressions:int, ctr:float, avg_position:float|null}
     */
    private function aggregateGsc(int $websiteId, string $start, string $end): array
    {
        $row = SearchConsoleData::query()
            ->where('website_id', $websiteId)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS position')
            ->first();

        $clicks = (int) ($row->clicks ?? 0);
        $impressions = (int) ($row->impressions ?? 0);
        $position = $row && $row->position !== null ? (float) $row->position : null;
        $ctr = $impressions > 0 ? $clicks / $impressions : 0.0;

        return ['clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $ctr, 'avg_position' => $position];
    }

    /**
     * Build the {value, prev, change_pct, direction} triple. `invert: true`
     * for metrics where lower = better (avg position) so the up/down arrow
     * reflects user expectation, not raw delta sign.
     */
    private function kpiTriple($current, $previous, bool $invert = false, int $decimals = 0, string $suffix = ''): array
    {
        $current = $current === null ? null : ($decimals > 0 ? round((float) $current, $decimals) : (int) $current);
        $previous = $previous === null ? null : ($decimals > 0 ? round((float) $previous, $decimals) : (int) $previous);

        $changePct = null;
        $direction = 'flat';
        if ($previous !== null && $current !== null && $previous != 0) {
            $changePct = round((($current - $previous) / abs($previous)) * 100, 1);
            if ($changePct > 0.5) $direction = $invert ? 'down' : 'up';
            elseif ($changePct < -0.5) $direction = $invert ? 'up' : 'down';
        }

        return [
            'value' => $current,
            'previous' => $previous,
            'change_pct' => $changePct,
            'direction' => $direction,
            'suffix' => $suffix,
        ];
    }

    /**
     * @return array{top_3:int, top_10:int, top_50:int, top_100:int}
     */
    private function positionDistribution(int $websiteId, string $start, string $end): array
    {
        $rows = SearchConsoleData::query()
            ->where('website_id', $websiteId)
            ->whereBetween('date', [$start, $end])
            ->where('query', '!=', '')
            ->selectRaw('query, AVG(position) AS pos')
            ->groupBy('query')
            ->get();

        $buckets = ['top_3' => 0, 'top_10' => 0, 'top_50' => 0, 'top_100' => 0];
        foreach ($rows as $r) {
            $pos = (float) $r->pos;
            if ($pos <= 3) $buckets['top_3']++;
            elseif ($pos <= 10) $buckets['top_10']++;
            elseif ($pos <= 50) $buckets['top_50']++;
            elseif ($pos <= 100) $buckets['top_100']++;
        }
        return $buckets;
    }

    /**
     * Daily click sparkline points for the given range — used by the Overview
     * card. Missing days fill as zeros so the path doesn't have gaps.
     *
     * @return list<array{date:string, clicks:int}>
     */
    private function dailyClicks(int $websiteId, string $start, string $end): array
    {
        $rows = SearchConsoleData::query()
            ->where('website_id', $websiteId)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('date, SUM(clicks) AS clicks')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('clicks', 'date')
            ->all();

        $out = [];
        $cursor = Carbon::parse($start);
        $stop = Carbon::parse($end);
        while ($cursor <= $stop) {
            $d = $cursor->toDateString();
            $out[] = ['date' => $d, 'clicks' => (int) ($rows[$d] ?? 0)];
            $cursor->addDay();
        }
        return $out;
    }

    /**
     * Top movers — keywords whose average position improved (gainers) or
     * regressed (losers) most over the range vs the immediately preceding
     * period. Pure GSC-derived; doesn't require Rank Tracker entries.
     *
     * @return list<array<string, mixed>>
     */
    private function topMovers(int $websiteId, string $start, string $end, string $direction, int $limit): array
    {
        $cur = SearchConsoleData::query()
            ->where('website_id', $websiteId)
            ->whereBetween('date', [$start, $end])
            ->where('query', '!=', '')
            ->selectRaw('query, AVG(position) AS pos, SUM(clicks) AS clicks, SUM(impressions) AS impressions')
            ->groupBy('query')
            ->havingRaw('SUM(impressions) >= 50')
            ->get()
            ->keyBy(fn ($r) => mb_strtolower((string) $r->query));

        $days = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
        $prevEnd = Carbon::parse($start)->subDay()->toDateString();
        $prevStart = Carbon::parse($prevEnd)->subDays($days - 1)->toDateString();

        $prev = SearchConsoleData::query()
            ->where('website_id', $websiteId)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->whereIn('query', $cur->pluck('query')->all())
            ->selectRaw('query, AVG(position) AS pos')
            ->groupBy('query')
            ->get()
            ->keyBy(fn ($r) => mb_strtolower((string) $r->query));

        $out = [];
        foreach ($cur as $key => $row) {
            $previousRow = $prev->get($key);
            if ($previousRow === null) continue;
            $delta = (float) $previousRow->pos - (float) $row->pos; // positive = improved
            $out[] = [
                'keyword' => (string) $row->query,
                'position' => round((float) $row->pos, 1),
                'previous_position' => round((float) $previousRow->pos, 1),
                'delta' => round($delta, 1),
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
            ];
        }
        usort($out, fn ($a, $b) => $direction === 'gainers' ? ($b['delta'] <=> $a['delta']) : ($a['delta'] <=> $b['delta']));
        return array_slice($out, 0, $limit);
    }
}
