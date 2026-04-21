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

        $out = [];
        foreach ($grouped as $page => $links) {
            if (! is_string($page) || $page === '') {
                continue;
            }
            $latest = $links->max('tracked_date');
            $latestDate = Carbon::parse($latest);
            $preStart = $latestDate->copy()->subDays($windowDays)->toDateString();
            $preEnd = $latestDate->copy()->subDay()->toDateString();
            $postStart = $latestDate->toDateString();
            $postEnd = $latestDate->copy()->addDays($windowDays - 1)->toDateString();

            $pre = (int) SearchConsoleData::query()
                ->where('website_id', $websiteId)
                ->where('page', $page)
                ->whereDate('date', '>=', $preStart)
                ->whereDate('date', '<=', $preEnd)
                ->sum('clicks');

            $post = (int) SearchConsoleData::query()
                ->where('website_id', $websiteId)
                ->where('page', $page)
                ->whereDate('date', '>=', $postStart)
                ->whereDate('date', '<=', $postEnd)
                ->sum('clicks');

            $change = $post - $pre;
            $pct = $pre > 0 ? round(($change / $pre) * 100, 1) : null;

            $avgDa = $links->whereNotNull('domain_authority')->avg('domain_authority');

            $out[] = [
                'target_page_url' => $page,
                'backlink_count' => $links->count(),
                'latest_tracked_date' => $latestDate->toDateString(),
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
}
