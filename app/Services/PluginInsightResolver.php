<?php

namespace App\Services;

use App\Models\PageAuditReport;
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
                'audited_at' => $audit->audited_at?->toIso8601String(),
                'performance_score_mobile' => isset($mobile['performance_score']) ? (int) $mobile['performance_score'] : null,
                'performance_score_desktop' => isset($desktop['performance_score']) ? (int) $desktop['performance_score'] : null,
                'lcp_ms_mobile' => isset($mobile['lcp_ms']) ? (int) $mobile['lcp_ms'] : null,
                'cls_mobile' => isset($mobile['cls']) ? (float) $mobile['cls'] : null,
            ];
        }

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
            ],
            'flags' => [
                'cannibalized' => ! empty($cannibalization),
                'striking_distance' => ! empty($striking),
                'tracked' => $trackedPayload !== null,
            ],
            'cannibalization' => $cannibalization,
            'striking_distance' => $striking,
            'tracked_keyword' => $trackedPayload,
            'audit' => $auditPayload,
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
}
