<?php

namespace App\Services\Crawler;

use App\Models\CrawlSite;
use App\Models\WebsitePage;
use App\Support\Crawler\CrawlValueRank;
use Illuminate\Support\Facades\DB;

/**
 * Derives the internal-link graph metrics for a shared crawl_site from the
 * discovered edges: inbound-link counts (→ orphan detection), click-depth from
 * the homepage via in-memory BFS (O(V+E), no SQL recursion), and the persisted
 * value_rank used for per-user cap windows.
 */
class SiteGraphAnalyzer
{
    public function analyze(CrawlSite $crawlSite): void
    {
        $crawlSiteId = $crawlSite->id;

        $this->recomputeInboundCounts($crawlSiteId);
        $this->recomputeClickDepth($crawlSite);
        // Persist the value ordering so reads can window pages by value_rank <= cap.
        CrawlValueRank::assign($crawlSiteId);
    }

    private function recomputeInboundCounts(string $crawlSiteId): void
    {
        // Reset, then set from aggregated discovered edges in a SINGLE join-update
        // (one query instead of one UPDATE per linked page).
        WebsitePage::where('crawl_site_id', $crawlSiteId)->update(['inbound_link_count' => 0]);

        DB::table('website_pages')
            ->where('website_pages.crawl_site_id', $crawlSiteId)
            ->joinSub(
                DB::table('website_internal_links')
                    ->where('crawl_site_id', $crawlSiteId)
                    ->where('status', 'discovered')
                    ->select('to_page_id', DB::raw('COUNT(*) as c'))
                    ->groupBy('to_page_id'),
                'edges',
                'edges.to_page_id',
                '=',
                'website_pages.id',
            )
            ->update(['inbound_link_count' => DB::raw('edges.c')]);
    }

    private function recomputeClickDepth(CrawlSite $crawlSite): void
    {
        $crawlSiteId = $crawlSite->id;

        // Adjacency list (from_page_id => [to_page_id, ...]) for discovered edges.
        // Stream with keyset pagination (lazyById = WHERE id > X), NOT chunk().
        $adjacency = [];
        foreach (
            DB::table('website_internal_links')
                ->where('crawl_site_id', $crawlSiteId)
                ->where('status', 'discovered')
                ->select('id', 'from_page_id', 'to_page_id')
                ->lazyById(5000, 'id') as $r
        ) {
            $adjacency[$r->from_page_id][] = $r->to_page_id;
        }

        $start = $this->homepageId($crawlSite);
        $depth = [];
        if ($start !== null) {
            $depth[$start] = 0;
            $queue = [$start];
            while ($queue !== []) {
                $node = array_shift($queue);
                foreach ($adjacency[$node] ?? [] as $next) {
                    if (! array_key_exists($next, $depth)) {
                        $depth[$next] = $depth[$node] + 1;
                        $queue[] = $next;
                    }
                }
            }
        }

        // Reset all to null (unreachable), then write computed depths in bulk grouped
        // by depth value — a handful of UPDATEs instead of one per page.
        WebsitePage::where('crawl_site_id', $crawlSiteId)->update(['click_depth' => null]);

        $idsByDepth = [];
        foreach ($depth as $id => $d) {
            $idsByDepth[$d][] = $id;
        }
        foreach ($idsByDepth as $d => $ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                WebsitePage::where('crawl_site_id', $crawlSiteId)->whereIn('id', $chunk)->update(['click_depth' => $d]);
            }
        }
    }

    private function homepageId(CrawlSite $crawlSite): ?string
    {
        $rootHash = WebsitePage::hashUrl($crawlSite->homepageUrl());
        $id = WebsitePage::where('crawl_site_id', $crawlSite->id)->where('url_hash', $rootHash)->value('id');
        if ($id) {
            return $id;
        }

        // Fallback: the indexable page with the shortest URL (closest to root).
        $page = WebsitePage::where('crawl_site_id', $crawlSite->id)
            ->whereNull('removed_at')
            ->orderByRaw('LENGTH(url) asc')
            ->orderBy('id')
            ->first();

        return $page?->id;
    }
}
