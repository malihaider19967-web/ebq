<?php

namespace App\Services;

use App\Models\Backlink;
use App\Models\SearchConsoleData;
use Illuminate\Support\Carbon;

class BacklinkImpactService
{
    /**
     * For each target page that received a backlink, compute the click delta
     * between the 28 days after tracked_date vs the 28 days before. Useful for
     * answering "did this link move the needle?"
     *
     * @return list<array{
     *     target_page_url: string,
     *     backlink_count: int,
     *     latest_tracked_date: string,
     *     pre_clicks: int,
     *     post_clicks: int,
     *     clicks_change: int,
     *     clicks_change_percent: ?float,
     *     avg_da: ?float,
     * }>
     */
    public function impactByTargetPage(int $websiteId, int $windowDays = 28, int $limit = 25): array
    {
        $backlinks = Backlink::query()
            ->where('website_id', $websiteId)
            ->whereNotNull('tracked_date')
            ->get(['target_page_url', 'tracked_date', 'domain_authority']);

        if ($backlinks->isEmpty()) {
            return [];
        }

        $grouped = $backlinks->groupBy('target_page_url');

        // Compute the widest date window once so we can fetch click data in a
        // single query for every target page, then bucket in PHP.
        $earliestPreStart = null;
        $latestPostEnd = null;
        $perPage = [];
        foreach ($grouped as $page => $links) {
            if (! is_string($page) || $page === '') {
                continue;
            }
            $latest = Carbon::parse($links->max('tracked_date'));
            $preStart = $latest->copy()->subDays($windowDays);
            $postEnd = $latest->copy()->addDays($windowDays - 1);
            $perPage[$page] = [
                'links' => $links,
                'latest' => $latest,
                'pre_start' => $preStart,
                'pre_end' => $latest->copy()->subDay(),
                'post_start' => $latest,
                'post_end' => $postEnd,
            ];
            if ($earliestPreStart === null || $preStart->lt($earliestPreStart)) {
                $earliestPreStart = $preStart;
            }
            if ($latestPostEnd === null || $postEnd->gt($latestPostEnd)) {
                $latestPostEnd = $postEnd;
            }
        }

        if (empty($perPage)) {
            return [];
        }

        $clickRows = SearchConsoleData::query()
            ->where('website_id', $websiteId)
            ->whereIn('page', array_keys($perPage))
            ->whereDate('date', '>=', $earliestPreStart->toDateString())
            ->whereDate('date', '<=', $latestPostEnd->toDateString())
            ->selectRaw('page, date, SUM(clicks) as clicks')
            ->groupBy('page', 'date')
            ->get();

        $clicksByPage = [];
        foreach ($clickRows as $row) {
            $dateKey = $row->date instanceof \Carbon\CarbonInterface
                ? $row->date->toDateString()
                : substr((string) $row->date, 0, 10);
            $clicksByPage[(string) $row->page][$dateKey] = (int) $row->clicks;
        }

        $out = [];
        foreach ($perPage as $page => $meta) {
            $pre = $this->sumClicksInRange($clicksByPage[$page] ?? [], $meta['pre_start'], $meta['pre_end']);
            $post = $this->sumClicksInRange($clicksByPage[$page] ?? [], $meta['post_start'], $meta['post_end']);
            $change = $post - $pre;
            $pct = $pre > 0 ? round(($change / $pre) * 100, 1) : null;

            $avgDa = $meta['links']->whereNotNull('domain_authority')->avg('domain_authority');

            $out[] = [
                'target_page_url' => $page,
                'backlink_count' => $meta['links']->count(),
                'latest_tracked_date' => $meta['latest']->toDateString(),
                'pre_clicks' => $pre,
                'post_clicks' => $post,
                'clicks_change' => $change,
                'clicks_change_percent' => $pct,
                'avg_da' => $avgDa !== null ? round((float) $avgDa, 1) : null,
            ];
        }

        usort($out, fn ($a, $b) => $b['clicks_change'] <=> $a['clicks_change']);

        return array_slice($out, 0, $limit);
    }

    /**
     * @param  array<string, int>  $clicksByDate
     */
    private function sumClicksInRange(array $clicksByDate, Carbon $start, Carbon $end): int
    {
        $total = 0;
        foreach ($clicksByDate as $date => $clicks) {
            if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                $total += $clicks;
            }
        }

        return $total;
    }
}
