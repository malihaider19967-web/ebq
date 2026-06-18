<?php

namespace App\Support\Crawler;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single source of the crawl "value ordering": GSC-trafficked → in sitemap →
 * shallow URL → id. Used both to pick which pages a capped crawl fetches first
 * (CrawlPassJob) and to assign the persisted value_rank on website_pages
 * (SiteGraphAnalyzer) so a user's cap window (value_rank <= cap) and the crawl
 * order can never drift apart.
 */
class CrawlValueRank
{
    /** Apply the value ordering to a website_pages query builder. */
    public static function order(Builder $query): Builder
    {
        return $query
            ->orderByRaw('source_gsc desc')
            ->orderByRaw('source_sitemap desc')
            ->orderByRaw('CHAR_LENGTH(url) asc')
            ->orderBy('id');
    }

    /**
     * Assign a dense 1..N value_rank over a crawl_site's live pages in value
     * order. Done in chunks so it scales to large sites without per-row updates.
     */
    public static function assign(string $crawlSiteId): void
    {
        $rank = 0;
        $ids = self::order(
            \App\Models\WebsitePage::query()
                ->where('crawl_site_id', $crawlSiteId)
                ->whereNull('removed_at')
        )->pluck('id');

        foreach ($ids->chunk(1000) as $chunk) {
            // CASE/WHEN bulk update keeps this to one query per 1000 pages.
            $cases = [];
            $bindings = [];
            foreach ($chunk as $id) {
                $rank++;
                $cases[] = 'WHEN ? THEN ?';
                $bindings[] = $id;
                $bindings[] = $rank;
            }
            // Bind the IN-list ids as parameters too — ULID ids are strings, so a
            // raw `IN (01KVB…)` interpolation would be parsed as column names.
            $inPlaceholders = implode(',', array_fill(0, $chunk->count(), '?'));
            foreach ($chunk as $id) {
                $bindings[] = $id;
            }
            DB::update(
                'UPDATE website_pages SET value_rank = CASE id '.implode(' ', $cases).' END WHERE id IN ('.$inPlaceholders.')',
                $bindings,
            );
        }
    }
}
