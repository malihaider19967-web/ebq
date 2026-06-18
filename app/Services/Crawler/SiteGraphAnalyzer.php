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
        // Aggregate inbound edges per target page in PHP by streaming the discovered
        // edges (keyset pagination, bounded memory: one int per linked page). The
        // result is written in bounded id-keyset chunks below.
        //
        // This replaces a whole-site `UPDATE ... JOIN` (and a whole-site reset). On
        // large sites (40k–168k pages) those single statements held InnoDB row locks
        // long enough to trip `innodb_lock_wait_timeout` (SQLSTATE 1205) — fatal here
        // because the finalize contends with live CrawlPageBatchJob writes to the same
        // website_pages rows. See infra/crawler/known-issues.md (2026-06-18 incident).
        $counts = [];
        foreach (
            DB::table('website_internal_links')
                ->where('crawl_site_id', $crawlSiteId)
                ->where('status', 'discovered')
                ->whereNotNull('to_page_id')
                ->select('id', 'to_page_id')
                ->lazyById(5000, 'id') as $r
        ) {
            $counts[$r->to_page_id] = ($counts[$r->to_page_id] ?? 0) + 1;
        }

        // Reset every page to 0 in bounded chunks, then write the non-zero counts
        // grouped by value — a handful of small UPDATEs instead of two table-wide ones.
        $this->resetColumnChunked($crawlSiteId, 'inbound_link_count', 0);
        $this->writeGroupedChunked($crawlSiteId, 'inbound_link_count', $counts);
    }

    /**
     * Set $column = $value for every page of the site in bounded id-keyset chunks so
     * each UPDATE locks at most ~2000 rows (a table-wide UPDATE trips 1205 mid-crawl).
     */
    private function resetColumnChunked(string $crawlSiteId, string $column, mixed $value): void
    {
        DB::table('website_pages')
            ->where('crawl_site_id', $crawlSiteId)
            ->select('id')
            ->orderBy('id')
            ->chunkById(2000, function ($rows) use ($column, $value): void {
                $ids = array_map(static fn ($r) => $r->id, $rows->all());
                if ($ids !== []) {
                    DB::table('website_pages')->whereIn('id', $ids)->update([$column => $value]);
                }
            }, 'id');
    }

    /**
     * Write $column from a [pageId => value] map, grouped by value and chunked to
     * 1000 ids per UPDATE (bounded lock scope). Pages absent from the map are left as
     * the caller reset them.
     *
     * @param  array<string,int>  $valueByPage
     */
    private function writeGroupedChunked(string $crawlSiteId, string $column, array $valueByPage): void
    {
        $idsByValue = [];
        foreach ($valueByPage as $id => $v) {
            $idsByValue[$v][] = $id;
        }
        foreach ($idsByValue as $v => $ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                WebsitePage::where('crawl_site_id', $crawlSiteId)
                    ->whereIn('id', $chunk)
                    ->update([$column => $v]);
            }
        }
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

        // Reset all to null (unreachable), then write computed depths grouped by depth
        // value — both in bounded chunks (table-wide UPDATEs trip 1205 mid-crawl, same
        // as the inbound-count pass above). Depth 0 (homepage) is a real value here, so
        // it is written by the grouped pass; only unreachable pages stay null.
        $this->resetColumnChunked($crawlSiteId, 'click_depth', null);
        $this->writeGroupedChunked($crawlSiteId, 'click_depth', $depth);
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
