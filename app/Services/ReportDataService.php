<?php

namespace App\Services;

use App\Models\AnalyticsData;
use App\Models\Backlink;
use App\Models\SearchConsoleData;
use Illuminate\Support\Carbon;

class ReportDataService
{
    public function generate(int $websiteId, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $days = $start->diffInDays($end) + 1;

        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

        $periodLabel = $this->periodLabel($days);
        $currentLabel = $this->currentPeriodLabel($days);
        $previousLabel = $this->previousPeriodLabel($days);

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'prev_start' => $prevStart->toDateString(),
                'prev_end' => $prevEnd->toDateString(),
                'days' => $days,
                'label' => $periodLabel,
                'current_label' => $currentLabel,
                'previous_label' => $previousLabel,
            ],
            'analytics' => $this->buildAnalytics($websiteId, $start, $end, $prevStart, $prevEnd),
            'search_console' => $this->buildSearchConsole($websiteId, $start, $end, $prevStart, $prevEnd),
            'backlinks' => $this->buildBacklinks($websiteId, $start, $end, $prevStart, $prevEnd),
        ];
    }

    private function buildAnalytics(int $websiteId, Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = AnalyticsData::where('website_id', $websiteId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        $previous = AnalyticsData::where('website_id', $websiteId)
            ->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()]);

        $curUsers = (int) (clone $current)->sum('users');
        $prevUsers = (int) (clone $previous)->sum('users');

        $curSessions = (int) (clone $current)->sum('sessions');
        $prevSessions = (int) (clone $previous)->sum('sessions');

        $curBounceCount = (clone $current)->count();
        $curBounceRate = $curBounceCount > 0
            ? round((float) (clone $current)->avg('bounce_rate'), 2)
            : 0.0;

        $prevBounceCount = (clone $previous)->count();
        $prevBounceRate = $prevBounceCount > 0
            ? round((float) (clone $previous)->avg('bounce_rate'), 2)
            : 0.0;

        $topSources = AnalyticsData::where('website_id', $websiteId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('source, SUM(users) as total_users, SUM(sessions) as total_sessions')
            ->groupBy('source')
            ->orderByDesc('total_users')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($websiteId, $prevStart, $prevEnd) {
                $prevUsers = (int) AnalyticsData::where('website_id', $websiteId)
                    ->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()])
                    ->where('source', $row->source)
                    ->sum('users');

                return [
                    'source' => $row->source ?: '(direct)',
                    'users' => (int) $row->total_users,
                    'sessions' => (int) $row->total_sessions,
                    'prev_users' => $prevUsers,
                    'change' => $this->calcChange((int) $row->total_users, $prevUsers, true),
                ];
            })
            ->toArray();

        return [
            'users' => $this->calcChange($curUsers, $prevUsers, true),
            'sessions' => $this->calcChange($curSessions, $prevSessions, true),
            'bounce_rate' => $this->calcChange($curBounceRate, $prevBounceRate, false),
            'top_sources' => $topSources,
        ];
    }

    private function buildSearchConsole(int $websiteId, Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = SearchConsoleData::where('website_id', $websiteId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        $previous = SearchConsoleData::where('website_id', $websiteId)
            ->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()]);

        $curClicks = (int) (clone $current)->sum('clicks');
        $prevClicks = (int) (clone $previous)->sum('clicks');

        $curImpressions = (int) (clone $current)->sum('impressions');
        $prevImpressions = (int) (clone $previous)->sum('impressions');

        $curPosCount = (clone $current)->count();
        $curPosition = $curPosCount > 0
            ? round((float) (clone $current)->avg('position'), 1)
            : 0.0;

        $prevPosCount = (clone $previous)->count();
        $prevPosition = $prevPosCount > 0
            ? round((float) (clone $previous)->avg('position'), 1)
            : 0.0;

        $curCtr = $curImpressions > 0
            ? round($curClicks / $curImpressions * 100, 2)
            : 0.0;
        $prevCtr = $prevImpressions > 0
            ? round($prevClicks / $prevImpressions * 100, 2)
            : 0.0;

        $topQueries = SearchConsoleData::where('website_id', $websiteId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('query, SUM(clicks) as total_clicks, SUM(impressions) as total_impressions, AVG(position) as avg_position, AVG(ctr) as avg_ctr')
            ->groupBy('query')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($websiteId, $prevStart, $prevEnd) {
                $prevClicks = (int) SearchConsoleData::where('website_id', $websiteId)
                    ->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()])
                    ->where('query', $row->query)
                    ->sum('clicks');

                return [
                    'query' => $row->query,
                    'clicks' => (int) $row->total_clicks,
                    'impressions' => (int) $row->total_impressions,
                    'position' => round((float) $row->avg_position, 1),
                    'ctr' => round((float) $row->avg_ctr * 100, 2),
                    'prev_clicks' => $prevClicks,
                    'change' => $this->calcChange((int) $row->total_clicks, $prevClicks, true),
                ];
            })
            ->toArray();

        $topPages = SearchConsoleData::where('website_id', $websiteId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('page, SUM(clicks) as total_clicks, SUM(impressions) as total_impressions, AVG(ctr) as avg_ctr')
            ->groupBy('page')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($websiteId, $prevStart, $prevEnd) {
                $prevClicks = (int) SearchConsoleData::where('website_id', $websiteId)
                    ->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()])
                    ->where('page', $row->page)
                    ->sum('clicks');

                return [
                    'page' => $row->page,
                    'clicks' => (int) $row->total_clicks,
                    'impressions' => (int) $row->total_impressions,
                    'ctr' => round((float) $row->avg_ctr * 100, 2),
                    'prev_clicks' => $prevClicks,
                    'change' => $this->calcChange((int) $row->total_clicks, $prevClicks, true),
                ];
            })
            ->toArray();

        $devices = SearchConsoleData::where('website_id', $websiteId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('device', '!=', '')
            ->selectRaw('device, SUM(clicks) as total_clicks')
            ->groupBy('device')
            ->orderByDesc('total_clicks')
            ->get()
            ->map(function ($row) use ($websiteId, $prevStart, $prevEnd, $curClicks) {
                $prevDevClicks = (int) SearchConsoleData::where('website_id', $websiteId)
                    ->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()])
                    ->where('device', $row->device)
                    ->sum('clicks');

                return [
                    'device' => $row->device ?: 'Unknown',
                    'clicks' => (int) $row->total_clicks,
                    'percentage' => $curClicks > 0 ? round((int) $row->total_clicks / $curClicks * 100, 1) : 0,
                    'prev_clicks' => $prevDevClicks,
                    'change' => $this->calcChange((int) $row->total_clicks, $prevDevClicks, true),
                ];
            })
            ->toArray();

        $countries = SearchConsoleData::where('website_id', $websiteId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('country', '!=', '')
            ->selectRaw('country, SUM(clicks) as total_clicks, SUM(impressions) as total_impressions')
            ->groupBy('country')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($websiteId, $prevStart, $prevEnd) {
                $prevClicks = (int) SearchConsoleData::where('website_id', $websiteId)
                    ->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()])
                    ->where('country', $row->country)
                    ->sum('clicks');

                return [
                    'country' => $row->country,
                    'clicks' => (int) $row->total_clicks,
                    'impressions' => (int) $row->total_impressions,
                    'prev_clicks' => $prevClicks,
                    'change' => $this->calcChange((int) $row->total_clicks, $prevClicks, true),
                ];
            })
            ->toArray();

        return [
            'clicks' => $this->calcChange($curClicks, $prevClicks, true),
            'impressions' => $this->calcChange($curImpressions, $prevImpressions, true),
            'position' => $this->calcChange($curPosition, $prevPosition, false),
            'ctr' => $this->calcChange($curCtr, $prevCtr, true),
            'top_queries' => $topQueries,
            'top_pages' => $topPages,
            'devices' => $devices,
            'countries' => $countries,
        ];
    }

    private function buildBacklinks(int $websiteId, Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = Backlink::where('website_id', $websiteId)
            ->whereBetween('tracked_date', [$start->toDateString(), $end->toDateString()]);

        $previous = Backlink::where('website_id', $websiteId)
            ->whereBetween('tracked_date', [$prevStart->toDateString(), $prevEnd->toDateString()]);

        $curCount = (clone $current)->count();
        $prevCount = (clone $previous)->count();

        $curDa = $curCount > 0 ? round((float) (clone $current)->avg('domain_authority'), 1) : 0;
        $prevDa = $prevCount > 0 ? round((float) (clone $previous)->avg('domain_authority'), 1) : 0;

        $curDofollow = (clone $current)->where('is_dofollow', true)->count();
        $prevDofollow = (clone $previous)->where('is_dofollow', true)->count();

        $curNofollow = $curCount - $curDofollow;
        $prevNofollow = $prevCount - $prevDofollow;

        $topBacklinks = Backlink::where('website_id', $websiteId)
            ->whereBetween('tracked_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('domain_authority')
            ->orderBy('referring_page_url')
            ->limit(20)
            ->get()
            ->map(fn ($b) => [
                'referring_page_url' => $b->referring_page_url,
                'target_page_url' => $b->target_page_url,
                'domain_authority' => $b->domain_authority,
                'spam_score' => $b->spam_score,
                'anchor_text' => $b->anchor_text,
                'type' => $b->type->label(),
                'is_dofollow' => $b->is_dofollow,
            ])
            ->toArray();

        return [
            'count' => $this->calcChange($curCount, $prevCount, true),
            'avg_da' => $this->calcChange($curDa, $prevDa, true),
            'dofollow' => $this->calcChange($curDofollow, $prevDofollow, true),
            'nofollow' => $this->calcChange($curNofollow, $prevNofollow, true),
            'top_backlinks' => $topBacklinks,
        ];
    }

    /**
     * @param bool $upIsPositive Whether an increase in this metric is good
     */
    private function calcChange(float|int $current, float|int $previous, bool $upIsPositive): array
    {
        $change = $current - $previous;
        $changePercent = $previous != 0
            ? round(($change / abs($previous)) * 100, 1)
            : ($current != 0 ? null : 0.0);

        if (abs($change) < 0.001) {
            $direction = 'flat';
        } else {
            $direction = $change > 0 ? 'up' : 'down';
        }

        $isPositive = match ($direction) {
            'up' => $upIsPositive,
            'down' => ! $upIsPositive,
            default => true,
        };

        return [
            'current' => $current,
            'previous' => $previous,
            'change' => round($change, 2),
            'change_percent' => $changePercent,
            'direction' => $direction,
            'is_positive' => $isPositive,
        ];
    }

    private function periodLabel(int $days): string
    {
        return match (true) {
            $days === 1 => 'Daily',
            $days <= 7 => 'Weekly',
            $days <= 31 => 'Monthly',
            default => 'Custom',
        };
    }

    private function currentPeriodLabel(int $days): string
    {
        return match (true) {
            $days === 1 => 'Today',
            $days <= 7 => 'This Week',
            $days <= 31 => 'This Month',
            default => "Last {$days} Days",
        };
    }

    private function previousPeriodLabel(int $days): string
    {
        return match (true) {
            $days === 1 => 'Yesterday',
            $days <= 7 => 'Prev Week',
            $days <= 31 => 'Prev Month',
            default => "Preceding {$days} Days",
        };
    }
}
