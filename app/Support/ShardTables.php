<?php

namespace App\Support;

/**
 * Single source of truth for which tables belong to each shard tier and how to
 * filter their rows for one tenant (a user's websites) or one crawl-site.
 *
 * Used by the tenant/crawl mover ({@see \App\Services\Sharding\ShardSetExporter})
 * and the app-level cascade cleanup (cross-connection FK cascades don't fire, so
 * deleting a website/crawl-site must delete these rows explicitly).
 *
 * The WHERE templates use `:ids` (a comma-separated, quoted list of the tenant's
 * website ULIDs) or `:cs` (a single quoted crawl_site ULID). Child tables filter
 * via a subquery on their in-tier parent so a row is never orphaned. Tables are
 * listed parent-before-child: import/insert in this order, delete in reverse.
 */
class ShardTables
{
    /** @var array<string,string> tenant-tier table => WHERE template (`:ids`) */
    public const TENANT = [
        'search_console_data' => 'website_id IN (:ids)',
        'analytics_data' => 'website_id IN (:ids)',
        'page_indexing_statuses' => 'website_id IN (:ids)',
        'backlinks' => 'website_id IN (:ids)',
        'ai_insights' => 'website_id IN (:ids)',
        'page_audit_reports' => 'website_id IN (:ids)',
        'custom_page_audits' => 'website_id IN (:ids)',
        'rank_tracking_keywords' => 'website_id IN (:ids)',
        'rank_tracking_snapshots' => 'rank_tracking_keyword_id IN (SELECT id FROM rank_tracking_keywords WHERE website_id IN (:ids))',
        'writer_projects' => 'website_id IN (:ids)',
        'brand_voice_profiles' => 'website_id IN (:ids)',
        'website_sitemaps' => 'website_id IN (:ids)',
        'keyword_alerts' => 'website_id IN (:ids)',
        'keyword_gap_analyses' => 'website_id IN (:ids)',
        'keyword_gap_rows' => 'keyword_gap_analysis_id IN (SELECT id FROM keyword_gap_analyses WHERE website_id IN (:ids))',
        'competitor_discovery_runs' => 'website_id IN (:ids)',
        'discovered_competitors' => 'website_id IN (:ids)',
        'outreach_prospects' => 'website_id IN (:ids)',
        'redirect_suggestions' => 'website_id IN (:ids)',
        'crawl_report_sends' => 'website_id IN (:ids)',
        'client_activities' => 'website_id IN (:ids)',
    ];

    /** @var array<string,string> crawl-tier table => WHERE template (`:cs`) */
    public const CRAWL = [
        'website_pages' => 'crawl_site_id = :cs',
        'website_internal_links' => 'crawl_site_id = :cs',
        'crawl_runs' => 'crawl_site_id = :cs',
        'crawl_findings' => 'crawl_site_id = :cs',
        'website_finding_states' => 'finding_id IN (SELECT id FROM crawl_findings WHERE crawl_site_id = :cs)',
    ];

    /** Build the WHERE clause for a tenant table given the website-id list. */
    public static function tenantWhere(string $table, array $websiteIds): string
    {
        $ids = self::quoteList($websiteIds);

        return str_replace(':ids', $ids === '' ? "''" : $ids, self::TENANT[$table]);
    }

    /** Build the WHERE clause for a crawl table given the crawl_site id. */
    public static function crawlWhere(string $table, string $crawlSiteId): string
    {
        return str_replace(':cs', "'".addslashes($crawlSiteId)."'", self::CRAWL[$table]);
    }

    /** @param array<int,string> $ids */
    private static function quoteList(array $ids): string
    {
        return implode(',', array_map(fn ($id) => "'".addslashes((string) $id)."'", $ids));
    }
}
